<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Admin;
use App\Model\AdminRole;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class EmployeeController extends Controller
{
    public function add_new()
    {
        $rls = AdminRole::whereNotIn('id', [1])->get();
        return view('admin-views.employee.add-new', compact('rls'));
    }

    public function store(Request $request)
    {
        //return $request;
        $request->validate([
            'name' => 'required',
            'role_id' => 'required',
            'image' => 'required',
            'email' => 'required|email|unique:admins',
            'phone'=>'required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required_with:password|same:password|min:8'

        ], [
            'name.required' => translate('Role name is required!'),
            'role_id.required' => translate('Role ID is required!'),
            'role_name.required' => translate('Role id is Required'),
            'email.required' => translate('Email id is Required'),
            'image.required' => translate('Image is Required'),

        ]);

        if ($request->role_id == 1) {
            Toastr::warning(translate('Access Denied!'));
            return back();
        }

        if ($request->has('image')) {
            $image_name = Helpers::upload('admin/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('admin/', 'png', $img);
                array_push($id_img_names, $identity_image);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        DB::table('admins')->insert([
            'f_name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'identity_number' => $request->identity_number,
            'identity_type' => $request->identity_type,
            'identity_image' => $identity_image,
            'admin_role_id' => $request->role_id,
            'password' => bcrypt($request->password),
            'status'=>1,
            'image' => $image_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        Toastr::success(translate('Employee added successfully!'));
        return redirect()->route('admin.employee.list');
    }

    function list(Request $request)
    {
        $search = $request['search'];
        $key = explode(' ', $request['search']);
        $em = Admin::with(['role'])->whereNotIn('id', [1])
                    ->when($search!=null, function($query) use($key){
                        foreach ($key as $value) {
                            $query->where('f_name', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%");
                        }
                    })
                    ->paginate(Helpers::getPagination());
        return view('admin-views.employee.list', compact('em','search'));
    }

    public function edit($id)
    {
        $e = Admin::where(['id' => $id])->first();
        $rls = AdminRole::whereNotIn('id', [1])->get();
        return view('admin-views.employee.edit', compact('rls', 'e'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'role_id' => 'required',
            'email' => 'required|email|unique:admins,email,'.$id,
            'password_confirmation' => 'required_with:password|same:password'
        ], [
            'name.required' => translate('name is required!'),
        ]);

        if ($request->role_id == 1) {
            Toastr::warning(translate('Access Denied!'));
            return back();
        }

        $e = Admin::find($id);
        if ($request['password'] == null) {
            $pass = $e['password'];
        } else {
            if (strlen($request['password']) < 7) {
                Toastr::warning(translate('Password length must be 8 character.'));
                return back();
            }
            $pass = bcrypt($request['password']);
        }


        if ($request->has('image')) {
            $e['image'] = Helpers::update('admin/', $e['image'], 'png', $request->file('image'));
        }

        if ($request->has('identity_image')){
            foreach (json_decode($e['identity_image'], true) as $img) {
                if (Storage::disk('public')->exists('admin/' . $img)) {
                    Storage::disk('public')->delete('admin/' . $img);
                }
            }
            $img_keeper = [];
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('admin/', 'png', $img);
                array_push($img_keeper, $identity_image);
            }
            $identity_image = json_encode($img_keeper);
        } else {
            $identity_image = $e['identity_image'];
        }

        DB::table('admins')->where(['id' => $id])->update([
            'f_name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'identity_number' => $request->identity_number,
            'identity_type' => $request->identity_type,
            'identity_image' => $identity_image,
            'admin_role_id' => $request->role_id,
            'password' => $pass,
            'image' => $e['image'],
            'updated_at' => now(),
        ]);

        Toastr::success(translate('Employee updated successfully!'));
        return redirect()->route('admin.employee.list');
    }

    public function status(Request $request)
    {
        $employee = Admin::find($request->id);
        $employee->status = $request->status;
        $employee->save();

        Toastr::success(translate('Employee status updated!'));
        return back();
    }

    public function delete(Request $request)
    {
        $employee = Admin::where('id', $request->id)->whereNotIn('id', [1])->first();
        $employee->delete();
        Toastr::success(translate('Employee removed!'));
        return back();
    }

    public function export()
    {
        $employees = Admin::whereNotIn('id', [1])->get();
        $storage = [];
        foreach($employees as $employee){
            $role = $employee->role ? $employee->role->name : '';
            $storage[] = [
                'name' => $employee['f_name'],
                'phone' => $employee['phone'],
                'email' => $employee['email'],
                'admin_role' => $role,
            ];
        }
        return (new FastExcel($storage))->download('employee.xlsx');
    }
}
