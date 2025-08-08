<tr>
    <tr>
    <td>{{ $index + 1 }}</td>
    <td>{{ $item->variant_value }}</td>
    <td>{{ $item->quantity }}</td>
    <td>₹ {{ number_format($item->rate, 2) }}</td>
    <td>₹ {{ number_format($item->total, 2) }}</td>
</tr>
