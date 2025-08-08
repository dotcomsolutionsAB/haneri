<tr>
    <td class="center-align">{{ $index + 1 }}</td>
    <td><img src="{{ Storage::url('uploads/products_pdf/' . $item->product_code . '.jpg') }}" alt="Product Image" style="height: 60px; width: 60px;"></td>
    <td>
    {{ $item->product_name }}<br>
    Part No: {{ $item->product_id }}<br>
</td>
    <td class="center-align">{{ $item->quantity }}</td>
    <td class="right-align">₹ {{ number_format((float)$item->rate, 2) }}</td>
    <td class="right-align">₹ {{ number_format((float)$item->price, 2) }}</td>
</tr>
