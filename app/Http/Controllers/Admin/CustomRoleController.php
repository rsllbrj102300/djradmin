<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\AdminRole;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class CustomRoleController extends Controller
{
    public function create(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $rl = AdminRole::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }else{
            $rl=AdminRole::whereNotIn('id',[1]);
        }
        $rl = $rl->latest()->paginate(Helpers::getPagination())->appends($query_param);

        return view('admin-views.custom-role.create',compact('rl', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:admin_roles',
        ],[
            'name.required'=>translate('Role name is required!')
        ]);

        if($request['modules'] == null) {
            Toastr::error(translate('Select at least one module permission'));
            return back();
        }

        DB::table('admin_roles')->insert([
            'name'=>$request->name,
            'module_access'=>json_encode($request['modules']),
            'status'=>1,
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        Toastr::success(translate('Role added successfully!'));
        return back();
    }

    public function edit($id)
    {
        $role=AdminRole::where(['id'=>$id])->first(['id','name','module_access']);
        return view('admin-views.custom-role.edit',compact('role'));
    }

    public function update(Request $request,$id)
    {
        $request->validate([
            'name' => 'required',
        ],[
            'name.required'=> translate('Role name is required!')
        ]);

        DB::table('admin_roles')->where(['id'=>$id])->update([
            'name'=>$request->name,
            'module_access'=>json_encode($request['modules']),
            'status'=>1,
            'updated_at'=>now()
        ]);


        Toastr::success(translate('Role updated successfully!'));
        return redirect(route('admin.custom-role.create'));
    }

    public function delete(Request $request)
    {
        $role = AdminRole::find($request->id);
        $role->delete();
        Toastr::success(translate('Role removed!'));
        return back();
    }

    public function status(Request $request)
    {
        $role = AdminRole::find($request->id);
        $role->status = $request->status;
        $role->save();
        Toastr::success(translate('Role status updated!'));
        return back();
    }

    public function export()
    {
        $roles = AdminRole::whereNotIn('id',[1])->get();
        $storage = [];
        foreach($roles as $role){

            $storage[] = [
                'name' => $role['name'],
                'module_access' => $role['module_access']
            ];
        }
        return (new FastExcel($storage))->download('admin-role.xlsx');
    }
}
