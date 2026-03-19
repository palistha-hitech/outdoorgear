@extends('shopify::layouts.master')

<style>
    .header {
        position: sticky;
        top: 0;
    }

    .container {
        width: 600px;
        height: 300px;
        overflow: auto;
    }

    h1 {
        color: green;
    }
</style>
@section('content')
    <h1 class="text-center">Orders Details </h1>
    <div class="container-fluid">
        <div class="table-responsive">
            <table class="table display" id="myTable">
                <thead style="position: sticky;top: 0" class="thead-dark">
                    <tr>
                        <th>S.N</th>
                        <th>OrderId</th>
                        <th>erply Sales DocumentID</th>
                        <th>erply Pending</th>
                        <th>newSystemOrderID</th>
                        <th>newSystemOrder Number</th>
                        <th>newSystem CustomerEmail</th>

                        <th>erplyTotal</th>
                        <th>order total</th>
                        <th>order subtotal</th>
                        <th>total items</th>
                        <th>order_created</th>
                        <th>order_completed</th>
                        <th>pendingOrder ProcessTime</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $key => $order)
                        <tr
                            class="{{ empty($order->erplySalesDocumentID) || $order->erplyTotal != $order->order_total ? 'bg-danger' : '' }}">
                            <td>{{ ++$key }}</td>
                            <td>{{ $order->orderID }}</td>
                            <td>{{ $order->erplySalesDocumentID ?? '-' }}</td>
                            <td>{{ $order->erplyPending }}</td>
                            <td>{{ $order->newSystemOrderID }}</td>
                            <td>{{ $order->newSystemOrderNumber }}</td>
                            <td>{{ $order->newSystemCustomerEmail }}</td>

                            <td>{{ $order->erplyTotal }}</td>
                            <td>$ {{ $order->order_total }}</td>
                            <td>$ {{ $order->order_subtotal }}</td>
                            <td>{{ $order->total_items }}</td>
                            <td>{{ $order->order_created }}</td>
                            <td>{{ $order->order_completed }}</td>
                            <td>{{ $order->pendingOrderProcessTime }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- {{ $orders->links() }} --}}
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.css" />

    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
@endsection
