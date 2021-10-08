<table border="1" style="border-collapse: collapse; width: 100%;">
    <thead>
    <tr>
        <th colspan="14">LIST SWAB {{ $form->date_from }} - {{ $form->date_to }} </th>
    </tr>
    <tr>
        <th  style="padding: 5px;" halign="center">No</th>
        <th  style="padding: 5px;" halign="center" >Swab Date</th>
        <th  style="padding: 5px;" halign="center" >Paper No</th>
        <th  style="padding: 5px;" halign="center" >Paper Date</th>
        <th  style="padding: 5px;" halign="center">Name</th>
        <th  style="padding: 5px;" halign="center" >NIK</th>
        <th  style="padding: 5px;" halign="center" >No HP</th>
        <th  style="padding: 5px;" halign="center" >Company</th>
        <th  style="padding: 5px;" >Department</th>
        <th  style="padding: 5px;" >Payment</th>
        <th  style="padding: 5px;" >Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($paper as $i => $itm)
        <tr>
            <td style="overflow:hidden;padding: 5px;">{{ ++$i }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->swab_date }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->paper_no }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->paper_date }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->user_name }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->id_card }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->no_hp }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->company }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->department }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ $itm->payment }}</td>
            <td style="overflow:hidden;padding: 5px;" valign="middle">{{ ($itm->is_complete === 'Y') ? 'Finish' : 'Pending' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
