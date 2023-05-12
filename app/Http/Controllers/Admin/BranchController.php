<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class   BranchController extends Controller
{
    public function index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
           $branches = Branch::where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%");
                        }
            })->orderBy('id', 'desc');
            $query_param = ['search' => $request['search']];
        }else{
           $branches = Branch::orderBy('id', 'desc');
        }
        $branches = $branches->paginate(Helpers::getPagination())->appends($query_param);
        return view('admin-views.branch.add-new', compact('branches','search'));
    }

    public function list(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $branches = Branch::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            })->orderBy('id', 'desc');
            $query_param = ['search' => $request['search']];
        }else{
            $branches = Branch::orderBy('id', 'desc');
        }
        $branches = $branches->paginate(Helpers::getPagination())->appends($query_param);
        return view('admin-views.branch.list', compact('branches','search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255|unique:branches',
            'email' => 'required|max:255|unique:branches',
            'password' => 'required|min:8|max:255',
            'image' => 'required|max:2048',
        ], [
            'name.required' => translate('Name is required!'),
            'name.unique' => translate('Name must be unique'),
            'email.required' => translate('Email is required!'),
            'email.unique' => translate('Email must be unique'),
            'password.required' => translate('Password is required!'),
            'Image.required' => translate('Image is required!'),
        ]);

        //image upload
        if (!empty($request->file('image'))) {
            $image_name = Helpers::upload('branch/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $branch = new Branch();
        $branch->name = $request->name;
        $branch->email = $request->email;
        $branch->phone = $request->phone;
        $branch->longitude = $request->longitude;
        $branch->latitude = $request->latitude;
        $branch->coverage = $request->coverage ? $request->coverage : 0;
        $branch->address = $request->address;
        $branch->password = bcrypt($request->password);
        $branch->image = $image_name;
        $branch->save();
        Toastr::success(translate('Branch added successfully!'));
        return redirect('admin/branch/list');
    }

    public function edit($id)
    {
        $branch = Branch::find($id);
        return view('admin-views.branch.edit', compact('branch'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:255',
            'email' => ['required', 'unique:branches,email,'.$id.',id']
        ], [
            'name.required' => translate('Name is required!'),
            'email.required' => translate('Email is required!'),
            'email.unique' => translate('Email must be unique!'),
        ]);

        $branch = Branch::find($id);
        $branch->name = $request->name;
        $branch->email = $request->email;
        $branch->phone = $request->phone;
        $branch->longitude = $request->longitude;
        $branch->latitude = $request->latitude;
        $branch->coverage = $request->coverage ? $request->coverage : 0;
        $branch->address = $request->address;
        $branch->image = $request->has('image') ? Helpers::update('branch/', $branch->image, 'png', $request->file('image')) : $branch->image;
        if ($request['password'] != null) {
            $branch->password = bcrypt($request->password);
        }
        $branch->save();
        Toastr::success(translate('Branch updated successfully!'));

        return back();
    }

    public function delete(Request $request)
    {
        $branch = Branch::where('id', $request->id)->whereNotIn('id', [1])->first();
        if ($branch){
            $branch->delete();
            Toastr::success(translate('Branch removed!'));
        }else{
            Toastr::warning(translate('Access denied!'));
        }
        return back();
    }

    public function status(Request $request)
    {
        $branch = Branch::find($request->id);
        $branch->status = $request->status;
        $branch->save();
        Toastr::success(translate('Branch status updated!'));
        return back();
    }
}
