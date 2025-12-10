<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperstoreSeeder extends Seeder
{
    public function run()
    {
        // Pastikan settingan baris PHP bisa membaca berbagai format enter (\n, \r)
        ini_set('auto_detect_line_endings', true);

        $path = storage_path('app/superstore.csv'); 
        
        if (!file_exists($path)) {
            $this->command->error("File superstore.csv tidak ditemukan!");
            return;
        }

        $file = fopen($path, 'r');
        
        // Skip Header (Baris 1)
        $header = fgetcsv($file, 0, ','); 

        $this->command->info('Mulai proses ETL (Versi Robust)...');

        $customerMap = []; 
        $productMap = [];
        $locationMap = [];
        $dateMap = [];

        $rowCount = 0;
        $successCount = 0;
        $batchSales = []; 

        DB::beginTransaction();

        try {
            // Kita paksa delimiter KOMA (,)
            while (($row = fgetcsv($file, 0, ',')) !== false) {
                $rowCount++;

                // --- VALIDASI ANTI-CRASH ---
                // Cek apakah baris ini punya minimal 18 kolom?
                // Kalau cuma 1 kolom (biasanya baris kosong/error parsing), SKIP.
                if (count($row) < 17) { 
                    $this->command->warn("Baris data ke-$rowCount dilewati (Format rusak/Kosong).");
                    continue; 
                }

                // Bersihkan karakter aneh di Sales
                $rawSales = isset($row[17]) ? str_replace([';', ','], ['', ''], $row[17]) : 0;
                // Kadang sales ada koma ribuan (e.g. "1,200.00"), kita hapus komanya biar jadi float valid
                if (is_string($rawSales)) {
                    $rawSales = preg_replace('/[^0-9\.]/', '', $rawSales);
                }
                $salesValue = floatval($rawSales);

                // DATA MAPPING
                $data = [
                    'order_id'      => $row[1] ?? 'UNKNOWN',
                    'order_date'    => $row[2] ?? date('d/m/Y'),
                    'ship_date'     => $row[3] ?? date('d/m/Y'),
                    'ship_mode'     => $row[4] ?? 'Standard',
                    'cust_id'       => $row[5] ?? 'C-000',
                    'cust_name'     => $row[6] ?? 'No Name',
                    'segment'       => $row[7] ?? 'Consumer',
                    'country'       => $row[8] ?? 'Indonesia',
                    'city'          => $row[9] ?? 'Unknown',
                    'state'         => $row[10] ?? 'Unknown',
                    'postal_code'   => $row[11] ?: '00000',
                    'region'        => $row[12] ?? 'None',
                    'prod_id'       => $row[13] ?? 'P-000',
                    'category'      => $row[14] ?? 'Other',
                    'sub_cat'       => $row[15] ?? 'Other',
                    'prod_name'     => $row[16] ?? 'Unknown Product',
                    'sales'         => $salesValue,
                    
                    // Dummy Values
                    'qty'           => 1,
                    'discount'      => 0,
                    'profit'        => $salesValue * 0.1, 
                ];

                // --- TRANSFORM ---

                // A. Dim Customer
                if (!isset($customerMap[$data['cust_id']])) {
                    $id = DB::table('dim_customers')->insertGetId([
                        'customer_source_id' => $data['cust_id'],
                        'customer_name'      => $data['cust_name'],
                        'segment'            => $data['segment'],
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                    $customerMap[$data['cust_id']] = $id;
                }
                $customerId = $customerMap[$data['cust_id']];

                // B. Dim Product
                $prodKey = $data['prod_id'] . '-' . substr($data['prod_name'], 0, 10); 
                if (!isset($productMap[$prodKey])) {
                    $id = DB::table('dim_products')->insertGetId([
                        'product_source_id' => $data['prod_id'],
                        'product_name'      => mb_strimwidth($data['prod_name'], 0, 500),
                        'category'          => $data['category'],
                        'sub_category'      => $data['sub_cat'],
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                    $productMap[$prodKey] = $id;
                }
                $productId = $productMap[$prodKey];

                // C. Dim Location
                $locKey = $data['city'] . '-' . $data['state'];
                if (!isset($locationMap[$locKey])) {
                    $id = DB::table('dim_locations')->insertGetId([
                        'country'     => $data['country'],
                        'city'        => $data['city'],
                        'state'       => $data['state'],
                        'postal_code' => $data['postal_code'],
                        'region'      => $data['region'],
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                    $locationMap[$locKey] = $id;
                }
                $locationId = $locationMap[$locKey];

                // D. Dim Date
                try {
                    $cleanOrderDate = str_replace('-', '/', $data['order_date']);
                    $dateObj = Carbon::createFromFormat('d/m/Y', $cleanOrderDate);
                } catch (\Exception $e) {
                    continue; // Skip tanggal error
                }
                
                $dateId = $dateObj->format('Ymd');
                
                if (!isset($dateMap[$dateId])) {
                    $exists = DB::table('dim_dates')->where('date_id', $dateId)->exists();
                    if (!$exists) {
                        DB::table('dim_dates')->insert([
                            'date_id'    => $dateId,
                            'full_date'  => $dateObj->format('Y-m-d'),
                            'year'       => $dateObj->year,
                            'month'      => $dateObj->month,
                            'month_name' => $dateObj->format('F'),
                            'quarter'    => $dateObj->quarter,
                            'created_at' => now(), 'updated_at' => now()
                        ]);
                    }
                    $dateMap[$dateId] = $dateId;
                }

                // Ship Date
                try {
                    $cleanShipDate = str_replace('-', '/', $data['ship_date']);
                    $shipDateObj = Carbon::createFromFormat('d/m/Y', $cleanShipDate);
                } catch (\Exception $e) {
                    $shipDateObj = $dateObj;
                }

                // --- LOAD ---
                $batchSales[] = [
                    'order_source_id' => $data['order_id'],
                    'customer_id'     => $customerId,
                    'product_id'      => $productId,
                    'location_id'     => $locationId,
                    'order_date_id'   => $dateId,
                    'ship_date'       => $shipDateObj->format('Y-m-d'),
                    'ship_mode'       => $data['ship_mode'],
                    'sales'           => $data['sales'],
                    'quantity'        => $data['qty'],
                    'discount'        => $data['discount'],
                    'profit'          => $data['profit'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                $successCount++;

                if (count($batchSales) >= 500) {
                    DB::table('fact_sales')->insert($batchSales);
                    $batchSales = [];
                    $this->command->info("Processed $successCount rows...");
                }
            }
            
            if (!empty($batchSales)) {
                DB::table('fact_sales')->insert($batchSales);
            }

            DB::commit();
            fclose($file);
            $this->command->info("SUKSES! Total $successCount baris berhasil masuk DB.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Error di baris $rowCount: " . $e->getMessage());
        }
    }
}