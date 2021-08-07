<table style="border-collapse: collapse;width: 100%;">
    <tr>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Item Code</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Item Name</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Category</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">UoM</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Req Qty</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Req Date</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Notes</th>
    </tr>
    <tr>
        @foreach ($details as $key => $value)
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["ItemCode"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["ItemName"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["ItemCategory"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["UoMCode"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["ReqQty"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ $value["ReqDate"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">{{ (array_key_exists('ReqNotes', $value)) ? $value["ReqNotes"] : '' }} </td>
        @endforeach
    </tr>
</table>
