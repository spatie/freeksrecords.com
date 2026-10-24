<?php

namespace App\Http\Controllers;

use App\Models\RecordList;
use App\Models\User;
use App\Support\SocialData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserListsController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        $lists = RecordList::previews($request->user())->where('user_id', $user->id)->paginate(12);

        return Inertia::render('social/ProfileListsPage', [
            'person' => SocialData::person($user),
            'listCount' => $lists->total(),
            'lists' => SocialData::pagination($lists, $lists->getCollection()->map(SocialData::listPreview(...))->all()),
        ]);
    }
}
