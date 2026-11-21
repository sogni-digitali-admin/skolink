<?php

use Illuminate\Support\Arr;

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{id}', function ($user, $id) {
    return $user->inConversation($id);
   
});

// Skolink messaging API: private channel per 1:1 conversation (uuid key).
Broadcast::channel('conversation.{uuid}', function ($user, $uuid) {
    return \App\Models\Conversation::where('uuid', $uuid)
        ->whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->exists();
});

Broadcast::channel('chat.{roomId}', function ($user, $id) {
   return Arr::only($user->toArray(),[
   	'id',
   	'name'
   	]);
});

Broadcast::channel('notification', function ($user) {
  return Auth::check();
});
