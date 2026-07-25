<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

/**
 * SKU pattern follows the client's own: <material><thickness>-<color>-<size>-<supplier>
 * where size 84 is the 8' x 4' (2440 x 1220 mm) sheet.
 */
class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->materials() as $material) {
            Material::updateOrCreate(['sku' => $material['sku']], $material);
        }
    }

    private function materials(): array
    {
        $sheet = ['sheet_w_mm' => 2440, 'sheet_h_mm' => 1220];

        return [
            [
                'sku' => 'AS3.0-430-84-XX', 'name' => 'Acrylic Cast 3.0mm Opal White', 'brand' => 'Perspex',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 3.0,
                'color_code' => '430', 'color_name' => 'Opal White',
                'selling_price_aed' => 180.00, 'stock_qty' => 120,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'AS5.0-430-84-XX', 'name' => 'Acrylic Cast 5.0mm Opal White', 'brand' => 'Perspex',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 5.0,
                'color_code' => '430', 'color_name' => 'Opal White',
                'selling_price_aed' => 295.00, 'stock_qty' => 80,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'AS2.8-2016-84-YL', 'name' => 'Acrylic Cast 2.8mm Dark Yellow', 'brand' => 'Plexiglas',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 2.8,
                'color_code' => '2016', 'color_name' => 'Dark Yellow',
                'selling_price_aed' => 172.00, 'stock_qty' => 40,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'AS3.0-000-84-XX', 'name' => 'Acrylic Cast 3.0mm Clear', 'brand' => 'Perspex',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 3.0,
                'color_code' => '000', 'color_name' => 'Clear',
                'selling_price_aed' => 165.00, 'stock_qty' => 150,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'AS5.0-000-84-XX', 'name' => 'Acrylic Cast 5.0mm Clear', 'brand' => 'Perspex',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 5.0,
                'color_code' => '000', 'color_name' => 'Clear',
                'selling_price_aed' => 270.00, 'stock_qty' => 90,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'AS10.0-000-84-XX', 'name' => 'Acrylic Cast 10.0mm Clear', 'brand' => 'Plexiglas',
                'material_group' => 'acrylic_cast', 'thickness_mm' => 10.0,
                'color_code' => '000', 'color_name' => 'Clear',
                'selling_price_aed' => 520.00, 'stock_qty' => 25,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                // Directional material: the mirror finish has a grain, so the
                // engine must never rotate a piece cut from it.
                'sku' => 'AM3.0-SLV-84-MR', 'name' => 'Acrylic Mirror 3.0mm Silver', 'brand' => 'Mirrolux',
                'material_group' => 'acrylic_mirror', 'thickness_mm' => 3.0,
                'color_code' => 'SLV', 'color_name' => 'Mirror Silver',
                'selling_price_aed' => 240.00, 'stock_qty' => 35,
                'rotation_allowed' => false, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'PC6.0-000-84-XX', 'name' => 'Polycarbonate Solid 6.0mm Clear', 'brand' => 'Palram',
                'material_group' => 'polycarbonate', 'thickness_mm' => 6.0,
                'color_code' => '000', 'color_name' => 'Clear',
                'selling_price_aed' => 385.00, 'stock_qty' => 60,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'PC4.0-000-84-XX', 'name' => 'Polycarbonate Solid 4.0mm Clear', 'brand' => 'Palram',
                'material_group' => 'polycarbonate', 'thickness_mm' => 4.0,
                'color_code' => '000', 'color_name' => 'Clear',
                'selling_price_aed' => 265.00, 'stock_qty' => 45,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'PC3.0-020-84-BR', 'name' => 'Polycarbonate Solid 3.0mm Bronze', 'brand' => 'Palram',
                'material_group' => 'polycarbonate', 'thickness_mm' => 3.0,
                'color_code' => '020', 'color_name' => 'Bronze',
                'selling_price_aed' => 230.00, 'stock_qty' => 30,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'HD5.0-900-84-XX', 'name' => 'HDPE 5.0mm Black', 'brand' => 'Simona',
                'material_group' => 'hdpe', 'thickness_mm' => 5.0,
                'color_code' => '900', 'color_name' => 'Black',
                'selling_price_aed' => 210.00, 'stock_qty' => 70,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'HD5.0-100-84-NT', 'name' => 'HDPE 5.0mm Natural', 'brand' => 'Simona',
                'material_group' => 'hdpe', 'thickness_mm' => 5.0,
                'color_code' => '100', 'color_name' => 'Natural',
                'selling_price_aed' => 205.00, 'stock_qty' => 55,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
            [
                'sku' => 'HD10.0-900-84-XX', 'name' => 'HDPE 10.0mm Black', 'brand' => 'Simona',
                'material_group' => 'hdpe', 'thickness_mm' => 10.0,
                'color_code' => '900', 'color_name' => 'Black',
                'selling_price_aed' => 395.00, 'stock_qty' => 20,
                'rotation_allowed' => true, 'is_cut_eligible' => true,
            ] + $sheet,
        ];
    }
}
