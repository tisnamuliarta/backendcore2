<table style="border-collapse: collapse;width: 100%;">
    <tr>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Item Code</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Item Name</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Required Date</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">UoM</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Req Qty</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Req Date</th>
        <th style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;">Notes</th>
    </tr>
    @foreach ($details as $key => $value)
        <tr>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ $value["ItemCode"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:30%">{{ $value["ItemName"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ $header->RequiredDate }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ $value["UoMCode"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ $value["ReqQty"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ $value["ReqDate"] }} </td>
            <td style="border: 1px solid #5e5b5b;text-align: left;padding: 4px;width:10%">{{ (array_key_exists('ReqNotes', $value)) ? $value["ReqNotes"] : '' }} </td>
        </tr>
    @endforeach
</table>
