<?php

namespace App\Services;

use App\Models\Order;
use Closure;
use RuntimeException;

/** Private durable files; never uses the database cache or session for requests. */
class DesignChangeStore
{
    private array $cache = [];

    public function root(): string
    {
        return storage_path('app/order-design-changes');
    }

    public function identity(Order $order): string
    {
        if (! $order->receipt_key) {
            throw new RuntimeException('This order has no private record identity.');
        }

        return hash('sha256', $order->receipt_key);
    }

    public function statePath(Order $order): string
    {
        return $this->root().'/orders/'.$this->identity($order).'/state.json';
    }

    public function forget(Order $order): void
    {
        unset($this->cache[$this->identity($order)]);
    }

    public function read(Order $order, bool $fresh = false): array
    {
        $key = $this->identity($order);
        if (! $fresh && isset($this->cache[$key])) return $this->cache[$key];
        $path = $this->statePath($order);
        if (! file_exists($path)) {
            if (file_exists(dirname($path).'/registered') || file_exists($this->root().'/pending/'.$key.'.json')) {
                throw new RuntimeException('Registered design change information is missing.');
            }
            return $this->cache[$key] = ['format' => 1, 'order_id' => $order->id, 'identity' => $key, 'requests' => []];
        }
        try {
            $contents = file_get_contents($path);
            if ($contents === false) throw new RuntimeException('Cannot read design changes.');
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            throw new RuntimeException('Design change information cannot be read safely.', 0, $error);
        }
        if (($state['format'] ?? null) !== 1 || ($state['identity'] ?? null) !== $key
            || ($state['order_id'] ?? null) !== $order->id || ! is_array($state['requests'] ?? null)) {
            throw new RuntimeException('Design change record identity does not match this order.');
        }
        if (isset($state['retention'])) {
            $retention = $state['retention'];
            if (! is_array($retention) || ! is_array($retention['pending_deletions'] ?? [])
                || (isset($retention['hold']) && (! is_array($retention['hold'])
                    || ! is_string($retention['hold']['reason'] ?? null)))) {
                throw new RuntimeException('Order retention information is invalid.');
            }
            foreach (['photos_expired_at', 'history_expired_at'] as $field) {
                if (isset($retention[$field]) && (! is_string($retention[$field])
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $retention[$field]))) {
                    throw new RuntimeException('Order retention timestamp is invalid.');
                }
            }
            foreach ($retention['pending_deletions'] ?? [] as $file) {
                if (! is_array($file) || ! is_string($file['disk'] ?? null) || ! is_string($file['file_path'] ?? null)) {
                    throw new RuntimeException('Order retention retry information is invalid.');
                }
            }
        }
        foreach ($state['requests'] as $id => $request) {
            if (! is_array($request) || ($request['id'] ?? null) !== $id
                || ! in_array($request['status'] ?? null, ['pending', 'applying', 'approved', 'declined', 'withdrawn', 'closed'], true)
                || ! isset($request['before'], $request['proposed'], $request['submitted_at'])) {
                throw new RuntimeException('Design change history is invalid.');
            }
        }
        $markerPath = $this->root().'/pending/'.$key.'.json';
        if (file_exists($markerPath)) {
            $marker = json_decode(file_get_contents($markerPath), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($marker['request_id']) || ! isset($state['requests'][$marker['request_id']])) {
                throw new RuntimeException('A design change write was interrupted. Restore its record before resuming preparation.');
            }
        }

        return $this->cache[$key] = $state;
    }

    /** Order row locks must be acquired before this lock, in every writer. */
    public function locked(Order $order, Closure $callback): mixed
    {
        $directory = dirname($this->statePath($order));
        $this->directory($directory);
        $handle = fopen($directory.'/lock', 'c+b');
        if (! $handle || ! flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock design changes.');
        try {
            return $callback($this->read($order, true));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function write(Order $order, array $state): void
    {
        $key = $this->identity($order);
        $pending = collect($state['requests'])->first(fn ($change) => in_array($change['status'], ['pending', 'applying'], true));
        $marker = $this->root().'/pending/'.$key.'.json';
        // Write the hold marker first. A failed state write must not release a hold.
        if ($pending) $this->atomic($marker, ['order_id' => $order->id, 'identity' => $key, 'request_id' => $pending['id']]);
        $this->atomic($this->statePath($order), $state);
        $registered = dirname($this->statePath($order)).'/registered';
        if (! file_exists($registered) && file_put_contents($registered, $key) === false) {
            throw new RuntimeException('Cannot register design change storage.');
        }
        if (! $pending && file_exists($marker) && ! unlink($marker)) {
            throw new RuntimeException('Cannot clear the design change hold marker.');
        }
        $this->cache[$key] = $state;
    }

    public function pendingOrderIds(): array
    {
        $identities = [];
        foreach (glob($this->root().'/pending/*.json') ?: [] as $path) {
            $marker = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($marker['order_id'], $marker['identity'])) throw new RuntimeException('Invalid design change queue.');
            $identities[(int) $marker['order_id']][] = $marker['identity'];
        }
        if (! $identities) return [];
        return Order::whereIn('id', array_keys($identities))->get(['id', 'receipt_key'])
            ->filter(fn ($order) => in_array($this->identity($order), $identities[$order->id], true))->pluck('id')->all();
    }

    private function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Private design change storage is unavailable.');
        }
    }

    private function atomic(string $path, array $value): void
    {
        $this->directory(dirname($path));
        $temp = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $data = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $handle = fopen($temp, 'xb');
        if (! $handle) throw new RuntimeException('Cannot write design change information.');
        try {
            if (fwrite($handle, $data) !== strlen($data) || ! fflush($handle)) throw new RuntimeException('Incomplete design change write.');
            if (function_exists('fsync') && ! fsync($handle)) throw new RuntimeException('Cannot flush design change information.');
        } catch (\Throwable $error) {
            fclose($handle);
            @unlink($temp);
            throw $error;
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
        if (! rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Cannot save design change information.');
        }
    }
}
