<table class="data {{ !empty($trendTable) ? 'trends' : '' }}">
    @php
        $widths = !empty($packageTable) ? [34, 11, 8, 16, 15, 16] : (!empty($trendTable) ? [14, 15, 15, 15, 13, 17, 11] : [60, 40]);
    @endphp
    <thead><tr>
        @foreach($section['headers'] as $index => $header)
            <th style="width: {{ $widths[$index] }}%" class="{{ $section['formats'][$index] === 'text' ? '' : 'numeric' }}">{{ $header }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @forelse($section['rows'] as $row)
            <tr>
                @foreach($row as $index => $value)
                    @php
                        $display = $section['formats'][$index] === 'money' ? number_format((float) $value, 2) : ($section['formats'][$index] === 'integer' ? number_format((int) $value) : $value);
                        $largeAmount = $section['formats'][$index] === 'money' && strlen($display) > 11;
                    @endphp
                    <td class="{{ $section['formats'][$index] === 'text' ? '' : 'numeric' }}" @if($largeAmount) style="font-size: {{ strlen($display) > 16 ? 7 : 7.5 }}pt" @endif>{{ $display }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td class="empty" colspan="{{ count($section['headers']) }}">{{ $section['empty'] ?? 'No records in this period.' }}</td></tr>
        @endforelse
        @if(isset($section['total']))
            <tr class="total">
                @foreach($section['total'] as $index => $value)
                    @php
                        $value = $index === 0 ? 'Total' : $value;
                        $display = $section['formats'][$index] === 'money' ? number_format((float) $value, 2) : ($section['formats'][$index] === 'integer' ? number_format((int) $value) : $value);
                        $largeAmount = $section['formats'][$index] === 'money' && strlen($display) > 11;
                    @endphp
                    <td class="{{ $section['formats'][$index] === 'text' ? '' : 'numeric' }}" @if($largeAmount) style="font-size: {{ strlen($display) > 16 ? 7 : 7.5 }}pt" @endif>{{ $display }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>

