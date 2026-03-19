<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Stock</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid;
            padding: 8px;
            text-align: left;
        }

        /*th {*/
        /*    background-color: #f2f2f2;*/
        /*}*/

        .center {
            text-align: center;
        }
    </style>
</head>

<body>
    <h3>
        Product SOH Failed List
    </h3>
    <table>
        <thead>
            <tr style="font-size: 12px">
                <th>
                    S.N
                </th>

                <th>
                    ERPLY ID
                </th>

                <th>
                    ERPLY SKU
                </th>

                <th>
                    TITLE
                </th>

                <th>
                    SHOPIFY PRODUCT ID
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result as $key => $val)
                <tr style="font-size: 12px">
                    <th scope="row">{{ $key + 1 }}</th>
                    <td>{{ $val['erply_id']  }}</td>
                    <td>{{ $val['handle'] }}</td>
                    <td>{{ $val['title'] }}</td>
                    <td>{{ $val['shopify_product_id'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>