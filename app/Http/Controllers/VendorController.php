<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\LoginLanding;
use App\Models\LoginLandingIp;
use App\Models\Vendor;

use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('vendors.vendors');
    }

    public function listVendors(Request $request)
    {
        $column = $request->get('column');
        $direction = $request->get('direction');
        $search = $request->get('search');
        $brands = Brand::select(
            'brands.id',
            'brands.name',
            'clients.name AS client_name',
            'uploads.filename',
            'brands.active'
        )
            ->leftjoin('uploads', 'uploads.id', 'brands.logo_path')
            ->leftJoin('clients', 'clients.id', 'brands.client_id')
            ->whereNull('brands.client_id');

        if ($search != null) {
            $brands = $brands->search($search);
        }

        $column = $column == 'status' ? 'active' : $column;

        if ($column && $direction) {
            $brands = $brands->orderBy($column, $direction);
        } else {
            $brands = $brands->orderBy('name', 'asc');
        }

        return response()->json($brands->paginate(30));
    }

    public function destroyVendor(Brand $brand, $id)
    {
        $vendor = Vendor::where('id', $id)->first();
        $vendor->delete();

        session()->flash('flash_message', 'Vendor was successfully disabled!');
        return redirect()->route('brands.vendors', $brand->id);
    }

    public function enableVendor(Brand $brand, $id)
    {
        $vendor = Vendor::where('id', $id)->withTrashed()->first();
        $vendor->restore();

        session()->flash('flash_message', 'Vendor was successfully enabled!');
        return redirect()->route('brands.vendors', $brand->id);
    }

    public function removeLandingIP(Request $request, $brand, $vendor, $landing, $ip)
    {
        // info('IP is ' . $ip);
        // info('LANDING ID is ' . $landing);

        $lli = LoginLandingIp::where(
            'id',
            $landing
        )->where(
            'ip',
            $ip
        )->first();
        if ($lli) {
            $lli->delete();
            session()->flash('flash_message', 'IP Address Removed');
        }

        return redirect()->route('brands.loginLanding', ['brand' => $brand, 'vendor' => $vendor]);
    }

    public function addLandingIP(Request $request, Brand $brand, Vendor $vendor)
    {
        $ll = LoginLanding::where(
            'vendors_id',
            $vendor->id
        )->first();

        if ($ll && $request->ip_addr && $request->ip_addr_comment) {
            $ip = new LoginLandingIp();
            $ip->login_landing_id = $ll->id;
            $ip->ip = $request->ip_addr;
            $ip->description = $request->ip_addr_comment;
            $ip->save();
        }

        return redirect()->route(
            'brands.loginLanding',
            [
                $brand,
                $vendor
            ]
        );
    }

    public function loginLanding(Brand $brand, Vendor $vendor)
    {
        $this_vendor = Vendor::select(
            'vendors.id',
            'brands.name',
            'vendors.vendor_label',
            'brands.address',
            'brands.city',
            'brands.state',
            'brands.zip',
            'vendors.grp_id',
            'brands.service_number'
        )->join(
            'brands',
            'vendors.vendor_id',
            'brands.id'
        )->where(
            'vendors.id',
            $vendor->id
        )->first();


        if ($this_vendor == null) {
            info('Invalid vendor?');
            abort(500);
        }

        $portal = LoginLanding::where('vendors_id', $this_vendor->id)->first();
        if (!$portal) {
            $ll = new LoginLanding();
            $ll->vendors_id = $this_vendor->id;
            $ll->slug = substr(sha1(rand()), 0, 8);
            $ll->self_onboard = 0;
            $ll->save();
            $portal = $ll;
        }

        $ips = LoginLandingIp::where('login_landing_id', $portal->id)->get();
        return view(
            'brands.loginLanding',
            [
                'brand' => $brand,
                'ips' => $ips,
                'portal' => $portal,
                'vendor' => $this_vendor,
            ]
        );
    }
}
