<?php

namespace App\Http\Controllers;

use App\Models\MasterItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MasterItemsController extends Controller
{
    public function index()
    {
        return view('master_items.index.index');
    }

    public function search(Request $request)
    {
        // log incoming params for easier debugging
        Log::debug('MasterItemsController@search', $request->only(['kode','nama','hargamin','hargamax']));

        try {
            $kode = $request->input('kode', null);
            $nama = $request->input('nama', null);
            $hargamin = $request->input('hargamin', null);
            $hargamax = $request->input('hargamax', null);

            $query = MasterItem::query();

            // Filter by kode if provided
            if (!is_null($kode) && $kode !== '') {
                $query->where('kode', $kode);
            }

            // Filter by nama if provided
            if (!is_null($nama) && $nama !== '') {
                $query->where('nama', 'LIKE', '%' . $nama . '%');
            }

            // Normalize harga inputs to numbers when possible
            $min = is_numeric($hargamin) ? floatval($hargamin) : null;
            $max = is_numeric($hargamax) ? floatval($hargamax) : null;

            // Apply price filtering safely
            if (!is_null($min) && !is_null($max)) {
                // If user accidentally swapped min/max, swap them
                if ($min > $max) {
                    [$min, $max] = [$max, $min];
                }
                $query->whereBetween('harga_beli', [$min, $max]);
            } elseif (!is_null($min)) {
                $query->where('harga_beli', '>=', $min);
            } elseif (!is_null($max)) {
                $query->where('harga_beli', '<=', $max);
            }

            // Select required columns and ensure consistent ordering
            $results = $query
                ->select('kode', 'nama', 'jenis', 'harga_beli', 'laba', 'supplier', 'image')
                ->orderBy('kode')
                ->get();

            return response()->json([
                'status' => 200,
                'data' => $results
            ], 200);
        } catch (\Throwable $e) {
            // Log full error and return 500 with message for frontend
            Log::error('Search Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function formView($method, $id = 0)
    {
        if ($method == 'new') {
            $item = null;
        } else {
            $item = MasterItem::find($id);
        }
        $data['item'] = $item;
        $data['method'] = $method;
        return view('master_items.form.index', $data);
    }

    public function singleView($kode)
    {
        $data['data'] = MasterItem::where('kode', $kode)->first();
        return view('master_items.single.index', $data);
    }

    public function formSubmit(Request $request, $method, $id = 0)
    {
        $request->validate([
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048' 
        ]);

        if ($method == 'new') {
            $data_item = new MasterItem;
            $kode = MasterItem::count('id');
            $kode = $kode + 1;
            $kode = str_pad($kode, 5, '0', STR_PAD_LEFT);
            sleep(3);
        } else {
            $data_item = MasterItem::find($id);
            $kode = $data_item->kode;
        }

        //image upload handle
        if ($request->hasFile('image')) {
            //menghapus gambar lama jika ada
            if ($data_item->image && Storage::exists('public/master_items/' . $data_item->image)) {
                Storage::delete('public/master_items/' . $data_item->image);
            }
            //upload gambar baru
            $imageName = $kode . '_' . time() . '.' . $request->image->extension();
            $request->image->storeAs('public/master_items', $imageName);
            $data_item->image = $imageName;
        }

        $data_item->nama = $request->nama;
        $data_item->harga_beli = $request->harga_beli;
        $data_item->laba = $request->laba;
        $data_item->kode = $kode;
        $data_item->supplier = $request->supplier;
        $data_item->jenis = $request->jenis;
        $data_item->save();

        return redirect('master-items');
    }

    public function deleteImage($id)
    {
        $item = MasterItem::find($id);

        if ($item && $item->image){
            if (Storage::exists('public/master_items/' . $item->image)) {
                Storage::delete('public/master_items/' . $item->image);
            }
            $item->image = null;
            $item->save();
        }

        return redirect()->back()->with('success', 'Foto berhasil dihapus.');
    }

    public function delete($id)
    {
        MasterItem::find($id)->delete();
        return redirect('master-items');
    }

    public function updateRandomData()
    {
        $data = MasterItem::get();
        foreach($data as $item)
        {
            $kode = $item->id;
            $kode = str_pad($kode, 5, '0', STR_PAD_LEFT);

            $item->harga_beli = rand(100,1000000);
            $item->laba = rand(10,99);
            $item->kode = $kode;
            $item->supplier = $this->getRandomSupplier();
            $item->jenis = $this->getRandomJenis();
            $item->save();
        }
    }

    private function getRandomSupplier()
    {
        $array = ['Tokopaedi','Bukulapuk','TokoBagas','E Commurz','Blublu'];
        $random = rand(0,4);
        return $array[$random];
    }

    private function getRandomJenis()
    {
        $array = ['Obat','Alkes','Matkes','Umum','ATK'];
        $random = rand(0,4);
        return $array[$random];
    }
}