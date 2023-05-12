<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Notification;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    function index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
           $notifications = Notification::where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('title', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                        }
            });
            $query_param = ['search' => $request['search']];
        }else{
           $notifications = new Notification;
        }
        $notifications = $notifications->latest()->paginate(Helpers::getPagination())->appends($query_param);
        return view('admin-views.notification.index', compact('notifications','search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:100',
            'description' => 'required|max:255',
            'image'=> 'required'
        ], [
            'title.required' => 'title is required!',
        ]);

        if ($request->has('image')) {
            $image_name = Helpers::upload('notification/', 'png', $request->file('image'));
        } else {
            $image_name = null;
        }

        $notification = new Notification;
        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->image = $image_name;
        $notification->status = 1;
        $notification->save();

        try {
            $notification->type = 'general';
            Helpers::send_push_notif_to_topic($notification);
        } catch (\Exception $e) {
            Toastr::warning(translate('Push notification failed!'));

        }

        Toastr::success(translate('Notification sent successfully!'));
        return back();
    }

    public function edit($id)
    {
        $notification = Notification::find($id);
        return view('admin-views.notification.edit', compact('notification'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'description' => 'required|max:255',
        ], [
            'title.required' => 'title is required!',
        ]);

        $notification = Notification::find($id);

        if ($request->has('image')) {
            $image_name = Helpers::update('notification/', $notification->image, 'png', $request->file('image'));
        } else {
            $image_name = $notification['image'];
        }

        $notification->title = $request->title;
        $notification->description = $request->description;
        $notification->image = $image_name;
        $notification->save();
        Toastr::success(translate('Notification updated successfully!'));
        return back();
    }

    public function status(Request $request)
    {
        $notification = Notification::find($request->id);
        $notification->status = $request->status;
        $notification->save();
        Toastr::success(translate('Notification status updated!'));
        return back();
    }

    public function delete(Request $request)
    {
        $notification = Notification::find($request->id);
        if (Storage::disk('public')->exists('notification/' . $notification['image'])) {
            Storage::disk('public')->delete('notification/' . $notification['image']);
        }
        $notification->delete();
        Toastr::success(translate('Notification removed!'));
        return back();
    }
}
