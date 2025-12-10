<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desain Data Warehouse - Star Schema (Sesuai Tugas BI)
     */
    public function up(): void
    {
        // 1. Tabel Dimensi: CUSTOMER [cite: 54]
        Schema::create('dim_customers', function (Blueprint $table) {
            $table->id('customer_id'); // Primary Key buatan kita
            $table->string('customer_source_id'); // ID Asli dari CSV (misal: AB-1001)
            $table->string('customer_name');
            $table->string('segment'); // Consumer, Corporate, Home Office
            $table->timestamps();
        });

        // 2. Tabel Dimensi: PRODUCT [cite: 52]
        Schema::create('dim_products', function (Blueprint $table) {
            $table->id('product_id'); // Primary Key buatan kita
            $table->string('product_source_id'); // ID Asli CSV (misal: OFF-LA-1002)
            $table->string('product_name', 500); // Nama produk panjang
            $table->string('category'); // Furniture, Technology, dll
            $table->string('sub_category'); // Phones, Chairs, dll
            $table->timestamps();
        });

        // 3. Tabel Dimensi: LOCATION / REGION [cite: 55]
        Schema::create('dim_locations', function (Blueprint $table) {
            $table->id('location_id');
            $table->string('country');
            $table->string('city');
            $table->string('state');
            $table->string('postal_code')->nullable();
            $table->string('region');
            $table->timestamps();
        });

        // 4. Tabel Dimensi: DATE [cite: 53]
        // Penting untuk analisis tren per bulan/tahun
        Schema::create('dim_dates', function (Blueprint $table) {
            $table->id('date_id'); // Format: 20231108 (Integer)
            $table->date('full_date'); // 2023-11-08
            $table->integer('year');
            $table->integer('month');
            $table->string('month_name');
            $table->integer('quarter'); // Kuartal 1, 2, 3, 4
            $table->timestamps();
        });

        // 5. Tabel Fakta: SALES [cite: 51]
        // Pusat dari Star Schema (Transaksi)
        Schema::create('fact_sales', function (Blueprint $table) {
            $table->id('fact_id');
            
            $table->string('order_source_id'); // Order ID Asli (CA-2016-152156)
            
            // Relasi ke Tabel Dimensi (Foreign Keys)
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('customer_id')->on('dim_customers');

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('product_id')->on('dim_products');

            $table->unsignedBigInteger('location_id');
            $table->foreign('location_id')->references('location_id')->on('dim_locations');

            $table->unsignedBigInteger('order_date_id'); // Relasi ke dim_dates
            // Kita tidak pakai foreign key constraint keras ke dim_dates untuk performa insert massal, tapi logikanya tetap relasi.

            $table->date('ship_date');
            $table->string('ship_mode');

            // Measures / Angka-angka [cite: 49]
            $table->decimal('sales', 10, 2);
            $table->integer('quantity');
            $table->decimal('discount', 5, 2);
            $table->decimal('profit', 10, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_sales');
        Schema::dropIfExists('dim_dates');
        Schema::dropIfExists('dim_locations');
        Schema::dropIfExists('dim_products');
        Schema::dropIfExists('dim_customers');
    }
};