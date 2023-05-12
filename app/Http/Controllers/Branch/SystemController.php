<?php

namespace App\Http\Controllers\Branch;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class SystemController extends Controller
{
    public function restaurant_data()
    {
        $new_order = DB::table('orders')->where(['branch_id' => auth('branch')->id(), 'checked' => 0])->count();
        return response()->json([
            'success' => 1,
            'data' => ['new_order' => $new_order]
        ]);
    }

    public function settings()
    {
        return view('branch-views.settings');
    }

    public function settings_update(Request $request)
    {
        $request->validate([
            'name' => 'required',
//            'email' => 'required',
        ]);

        $branch = Branch::find(auth('branch')->id());

        if ($request->has('image')) {
            $image_name =Helpers::update('branch/', $branch->image, 'png', $request->file('image'));
        } else {
            $image_name = $branch['image'];
        }

        $branch->name = $request->name;
        //$branch->email = $request->email;
        $branch->phone = $request->phone;
        $branch->image = $image_name;
        $branch->save();
        Toastr::success(translate('Branch updated successfully!'));
        return back();
    }

    public function settings_password_update(Request $request)
    {
        $request->validate([
            'password' => 'required|same:confirm_password|min:8|max:255',
            'confirm_password' => 'required|max:255',
        ]);

        $branch = Branch::find(auth('branch')->id());
        $branch->password = bcrypt($request['password']);
        $branch->save();
        Toastr::success(translate('Branch password updated successfully!'));
        return back();
    }
}
