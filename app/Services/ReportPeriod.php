<?php

namespace App\Services;

use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReportPeriod
{
    public readonly string $timezone;
    public readonly CarbonImmutable $start;
    public readonly CarbonImmutable $end;

    public function __construct(string $start, string $end, public readonly array $filters = [])
    {
        $this->timezone = config('bakery.business_timezone', config('bakery.pickup_timezone', config('app.timezone')));
        $this->start = CarbonImmutable::parse($start, $this->timezone)->startOfDay();
        $this->end = CarbonImmutable::parse($end, $this->timezone)->startOfDay();
        if ($this->end->lt($this->start)) throw ValidationException::withMessages(['end_date' => 'The ending period must be on or after the start.']);
    }

    public static function resolve(array $input): self
    {
        $mode = $input['mode'] ?? (isset($input['start_date']) || isset($input['end_date']) ? 'custom' : 'preset');
        $input['mode'] = $mode;
        $data = Validator::make($input, [
            'mode' => ['required', 'in:month,month_range,custom,preset'],
            'month' => ['required_if:mode,month', 'nullable', 'date_format:Y-m'],
            'start_month' => ['required_if:mode,month_range', 'nullable', 'date_format:Y-m'],
            'end_month' => ['required_if:mode,month_range', 'nullable', 'date_format:Y-m'],
            'start_date' => ['required_if:mode,custom', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_if:mode,custom', 'nullable', 'date_format:Y-m-d'],
            'preset' => ['nullable', 'in:this_month,last_month,this_year'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        $timezone = config('bakery.business_timezone', config('bakery.pickup_timezone', config('app.timezone')));
        $today = isset($data['as_of']) ? CarbonImmutable::parse($data['as_of'], $timezone) : CarbonImmutable::now($timezone)->startOfDay();
        if ($mode === 'month') {
            $start = CarbonImmutable::parse($data['month'].'-01', $timezone); $end = $start->endOfMonth();
            $filters = ['mode' => $mode, 'month' => $data['month']];
        } elseif ($mode === 'month_range') {
            $start = CarbonImmutable::parse($data['start_month'].'-01', $timezone); $end = CarbonImmutable::parse($data['end_month'].'-01', $timezone)->endOfMonth();
            if ($end->lt($start)) throw ValidationException::withMessages(['end_month' => 'Choose an ending month on or after the starting month.']);
            $filters = ['mode' => $mode, 'start_month' => $data['start_month'], 'end_month' => $data['end_month']];
        } elseif ($mode === 'custom') {
            $start = CarbonImmutable::parse($data['start_date'], $timezone); $end = CarbonImmutable::parse($data['end_date'], $timezone);
            $filters = ['mode' => $mode, 'start_date' => $data['start_date'], 'end_date' => $data['end_date']];
        } else {
            $preset = $data['preset'] ?? 'this_month';
            [$start, $end] = match ($preset) {
                'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
                'this_year' => [$today->startOfYear(), $today], default => [$today->startOfMonth(), $today],
            };
            $filters = ['mode' => 'preset', 'preset' => $preset, 'as_of' => $today->toDateString()];
        }
        return new self($start->toDateString(), $end->toDateString(), $filters);
    }

    public static function dates(string|Carbon $start, string|Carbon $end): self
    {
        return new self(Carbon::parse($start)->toDateString(), Carbon::parse($end)->toDateString());
    }

    public function apply($query, string $column, bool $dateOnly = false)
    {
        if ($dateOnly) return $query->where($column, '>=', $this->start->toDateString())->where($column, '<', $this->end->addDay()->toDateString());
        // Half-open bounds include every timestamp on the last business day, including fractional seconds.
        return $query->where($column, '>=', $this->start->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'))
            ->where($column, '<', $this->end->addDay()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function label(): string { return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y'); }
    public function query(): array { return $this->filters ?: ['mode' => 'custom', 'start_date' => $this->start->toDateString(), 'end_date' => $this->end->toDateString()]; }
}
