<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsByModule = [
            'dashboard' => [
                ['code' => 'dashboard.view', 'name' => 'Melihat Dashboard'],
            ],
            'users' => [
                ['code' => 'users.manage', 'name' => 'Mengelola Pengguna'],
                ['code' => 'roles.manage', 'name' => 'Mengelola Peran & Hak Akses'],
            ],
            'catalog' => [
                ['code' => 'products.view', 'name' => 'Melihat Daftar Produk & Harga'],
                ['code' => 'products.create', 'name' => 'Membuat Produk Baru'],
                ['code' => 'products.update', 'name' => 'Mengubah Data Produk'],
                ['code' => 'products.deactivate', 'name' => 'Menonaktifkan Produk'],
                ['code' => 'catalog.manage', 'name' => 'Mengelola Kategori & Satuan'],
            ],
            'suppliers' => [
                ['code' => 'suppliers.view', 'name' => 'Melihat Daftar Pemasok'],
                ['code' => 'suppliers.manage', 'name' => 'Mengelola Data Pemasok'],
            ],
            'purchases' => [
                ['code' => 'purchases.create', 'name' => 'Membuat Purchase Order'],
                ['code' => 'purchases.confirm', 'name' => 'Konfirmasi Purchase Order'],
                ['code' => 'purchases.cancel', 'name' => 'Membatalkan Purchase Order'],
                ['code' => 'receipts.create', 'name' => 'Mencatat Penerimaan Barang'],
                ['code' => 'receipts.post', 'name' => 'Posting Penerimaan Barang & HPP'],
                ['code' => 'supplier_payments.create', 'name' => 'Mencatat Pembayaran Supplier'],
                ['code' => 'supplier_payments.reverse', 'name' => 'Reversal Pembayaran Supplier'],
            ],
            'sales' => [
                ['code' => 'sales.create', 'name' => 'Melakukan Transaksi POS / Penjualan'],
                ['code' => 'sales.discount.within_limit', 'name' => 'Menerapkan Diskon Kasir Standar'],
                ['code' => 'sales.discount.override', 'name' => 'Menyetujui Diskon Khusus Di Atas Batas'],
                ['code' => 'payments.verify_transfer', 'name' => 'Verifikasi Pembayaran Transfer'],
                ['code' => 'sales.view_all', 'name' => 'Melihat Seluruh Riwayat Transaksi Toko'],
                ['code' => 'sales.view_own', 'name' => 'Melihat Riwayat Transaksi Sendiri'],
            ],
            'returns' => [
                ['code' => 'returns.request', 'name' => 'Mengajukan Retur Penjualan'],
                ['code' => 'returns.approve', 'name' => 'Menyetujui / Menolak Retur Penjualan'],
                ['code' => 'returns.refund', 'name' => 'Membayar Pengembalian Dana (Refund)'],
            ],
            'expenses' => [
                ['code' => 'expenses.create', 'name' => 'Mencatat Biaya Operasional'],
                ['code' => 'expenses.post', 'name' => 'Posting Biaya Operasional'],
                ['code' => 'expenses.pay', 'name' => 'Membayar Biaya Operasional'],
            ],
            'inventory' => [
                ['code' => 'inventory.view', 'name' => 'Melihat Saldo & Kartu Stok'],
                ['code' => 'inventory.adjust', 'name' => 'Melakukan Penyesuaian Stok Khusus'],
            ],
            'stock_opname' => [
                ['code' => 'stock_opname.create', 'name' => 'Membuat Sesi Stok Opname'],
                ['code' => 'stock_opname.count', 'name' => 'Mengisi Hasil Hitung Fisik'],
                ['code' => 'stock_opname.approve', 'name' => 'Menyetujui Hasil Stok Opname'],
                ['code' => 'stock_opname.post', 'name' => 'Posting Penyesuaian Stok Opname'],
            ],
            'reports' => [
                ['code' => 'reports.view_all', 'name' => 'Melihat Semua Laporan Finansial & Operasional'],
                ['code' => 'reports.export', 'name' => 'Mengekspor Laporan (PDF / Excel)'],
                ['code' => 'reports.view_limited', 'name' => 'Melihat Laporan Operasional Terbatas'],
            ],
            'store_settings' => [
                ['code' => 'store_settings.manage', 'name' => 'Mengelola Profil Toko & Pengaturan Struk'],
            ],
            'audit' => [
                ['code' => 'audit.view', 'name' => 'Melihat Riwayat Jejak Audit'],
            ],
        ];

        $permissionModels = [];
        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $perm) {
                $permissionModels[$perm['code']] = Permission::updateOrCreate(
                    ['code' => $perm['code']],
                    [
                        'name' => $perm['name'],
                        'module' => $module,
                    ]
                );
            }
        }

        // Roles definition
        $adminRole = Role::updateOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Administrator penuh toko dengan seluruh kewenangan pengelolaan dan pelaporan.',
                'is_system' => true,
            ]
        );

        $supervisorRole = Role::updateOrCreate(
            ['code' => 'supervisor'],
            [
                'name' => 'Supervisor',
                'description' => 'Pengawas operasional toko dengan wewenang persetujuan verifikasi transfer, retur, dan stok opname.',
                'is_system' => true,
            ]
        );

        $cashierRole = Role::updateOrCreate(
            ['code' => 'cashier'],
            [
                'name' => 'Kasir',
                'description' => 'Petugas kasir untuk melayani transaksi penjualan dan pengajuan retur.',
                'is_system' => true,
            ]
        );

        // Assign permissions to Admin (All)
        $adminRole->permissions()->sync(array_values(array_map(fn ($p) => $p->id, $permissionModels)));

        // Assign permissions to Supervisor
        $supervisorPermissions = [
            'dashboard.view',
            'products.view',
            'suppliers.view',
            'suppliers.manage',
            'purchases.create',
            'purchases.confirm',
            'purchases.cancel',
            'receipts.create',
            'receipts.post',
            'supplier_payments.create',
            'sales.create',
            'sales.discount.within_limit',
            'sales.discount.override',
            'payments.verify_transfer',
            'sales.view_all',
            'sales.view_own',
            'returns.request',
            'returns.approve',
            'expenses.create',
            'expenses.post',
            'expenses.pay',
            'inventory.view',
            'inventory.adjust',
            'stock_opname.create',
            'stock_opname.count',
            'stock_opname.approve',
            'reports.view_limited',
            'audit.view',
        ];

        $supervisorIds = [];
        foreach ($supervisorPermissions as $code) {
            if (isset($permissionModels[$code])) {
                $supervisorIds[] = $permissionModels[$code]->id;
            }
        }
        $supervisorRole->permissions()->sync($supervisorIds);

        // Assign permissions to Kasir
        $cashierPermissions = [
            'dashboard.view',
            'products.view',
            'sales.create',
            'sales.discount.within_limit',
            'sales.view_own',
            'returns.request',
        ];

        $cashierIds = [];
        foreach ($cashierPermissions as $code) {
            if (isset($permissionModels[$code])) {
                $cashierIds[] = $permissionModels[$code]->id;
            }
        }
        $cashierRole->permissions()->sync($cashierIds);
    }
}
