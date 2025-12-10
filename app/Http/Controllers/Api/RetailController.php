<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetailController extends Controller
{
    public function dashboard()
    {
        $totalSales = DB::table('fact_sales')->sum('sales');

        $salesByCategory = DB::table('fact_sales')
            ->join('dim_products', 'fact_sales.product_id', '=', 'dim_products.product_id')
            ->select('dim_products.category', DB::raw('SUM(fact_sales.sales) as total'))
            ->groupBy('dim_products.category')
            ->get();

        $topProducts = DB::table('fact_sales')
            ->join('dim_products', 'fact_sales.product_id', '=', 'dim_products.product_id')
            ->select('dim_products.product_name', DB::raw('SUM(fact_sales.sales) as total_sales'))
            ->groupBy('dim_products.product_id', 'dim_products.product_name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        $monthlyTrend = DB::table('fact_sales')
            ->join('dim_dates', 'fact_sales.order_date_id', '=', 'dim_dates.date_id')
            ->select(
                'dim_dates.year',
                'dim_dates.month_name',
                DB::raw('SUM(fact_sales.sales) as total')
            )
            ->groupBy('dim_dates.year', 'dim_dates.month', 'dim_dates.month_name')
            ->orderBy('dim_dates.year', 'desc')
            ->orderBy('dim_dates.month', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->values(); 

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_sales' => round($totalSales, 2),
                'sales_by_category' => $salesByCategory,
                'top_products' => $topProducts,
                'monthly_sales_trend' => $monthlyTrend,
            ]
        ]);
    }

    public function products(Request $request)
    {
        $query = DB::table('dim_products');

        if ($request->has('search')) {
            $keyword = $request->search;
            $query->where('product_name', 'like', "%$keyword%");
        }

        $products = $query->paginate(20);

        return response()->json($products);
    }

    public function storeTransaction(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:dim_products,product_id',
            'quantity' => 'required|integer|min:1',
            'sales' => 'required|numeric',
        ]);

        DB::table('fact_sales')->insert([
            'order_source_id' => 'MOB-' . time(),
            'customer_id'     => 1, 
            'product_id'      => $request->product_id,
            'location_id'     => 1, 
            'order_date_id'   => date('Ymd'),
            'ship_date'       => date('Y-m-d'),
            'ship_mode'       => 'Standard Class',
            'sales'           => $request->sales,
            'quantity'        => $request->quantity,
            'discount'        => 0,
            'profit'          => $request->sales * 0.1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil disimpan!'
        ]);
    }
}