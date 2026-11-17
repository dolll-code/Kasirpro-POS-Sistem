<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StoreProfile;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $stats = [
            'products_count' => Product::count(),
            'products_active' => Product::where('is_active', true)->count(),
            'categories_count' => Category::count(),
            'units_count' => Unit::count(),
            'suppliers_count' => Supplier::count(),
            'expense_categories_count' => ExpenseCategory::count(),
            'cash_accounts_count' => CashAccount::count(),
            'store_profile' => StoreProfile::current(),
        ];

        return view('master.index', compact('stats'));
    }
}
