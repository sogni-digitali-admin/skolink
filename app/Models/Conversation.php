<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Message;

/**
 * Class Conversation
 *
 * Model for managing conversations between users.
 *
 * @property int $id
 * @property string $uuid
 * @property \DateTime $last_message_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $others
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message[] $messages
 * @mixin \Eloquent
 */
class Conversation extends Model
{
    /**
     * The table associated with the model.
     *
     * The legacy `conversations` table belongs to the old feedback
     * messaging pair (user_one/user_two); the chat stack this model
     * backs lives in conversation_chat / conversation_user /
     * conversation_messages.
     *
     * @var string
     */
    protected $table = 'conversation_chat';

	protected $fillable = [
		'last_message_at',
         'uuid',
         'school_id'

	];

	protected $dates = [
		'last_message_at'


	];

    /**
     * Get the route key name for the model.
     *
     * @return string
     */
	public function getRouteKeyName()
	{
		return 'uuid';
	}

    /**
     * Get users participating in this conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany('App\Models\User')->withPivot('read_at')
            ->withTimestamps()
        	->oldest('users.created_at');


    }

    /**
     * Get other users in this conversation (excluding authenticated user).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function others()
    {
        return $this->users()->where('user_id','!=',auth()->id());
    }

    /**
     * Get messages in this conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
    	return $this->hasMany('App\Models\Message')->latest();
    }

    /**
     * Check whether the given user participates in this conversation.
     *
     * @param int $userId
     * @return bool
     */
    public function isParticipant($userId)
    {
        return $this->users()->where('users.id', $userId)->exists();
    }

    /**
     * Get the other participant of a 1:1 conversation.
     *
     * @param \App\Models\User $user
     * @return \App\Models\User|null
     */
    public function otherParticipant($user)
    {
        return $this->users()->where('users.id', '!=', $user->id)->first();
    }

    /**
     * Count messages sent by others after the user's pivot read_at marker.
     *
     * @param \App\Models\User $user
     * @return int
     */
    public function unreadCountFor($user)
    {
        $pivot = $this->users()->where('users.id', $user->id)->first()?->pivot;

        $query = $this->messages()->where('user_id', '!=', $user->id);

        if ($pivot && $pivot->read_at) {
            $query->where('created_at', '>', $pivot->read_at);
        }

        return $query->count();
    }
}
