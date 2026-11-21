<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Helpers\SiteHelper;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\StudentAcademic;
use App\Models\StudentParentLink;
use App\Models\Teacherlink;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Class MessagingController
 *
 * Skolink 1:1 internal messaging API (PWA contract, /api/v2/conversations*).
 *
 * Scope rules enforced server-side:
 * - staff (usergroups 3,4,5,8,10,11,12,13) of the same school may converse
 *   with each other;
 * - teacher (5) <-> parent (7) only when the parent is linked
 *   (student_parent_links) to a student in a standardLink the teacher
 *   teaches in the current academic year;
 * - parent <-> parent and all student (6) access is forbidden;
 * - every conversation is school-scoped (same school_id on both sides).
 *
 * Teacher clients use the same Sanctum token guard as parents
 * (Api\Teacher\LoginController issues personal access tokens on the users
 * table), so this single controller serves both; no teacherapi duplicate.
 */
class MessagingController extends Controller
{
    /**
     * Staff usergroups allowed to chat with each other.
     *
     * @var int[]
     */
    const STAFF_USERGROUPS = [
        User::SCHOOLADMIN_USERGROUP_ID,
        User::SCHOOLSUBADMIN_USERGROUP_ID,
        User::TEACHER_USERGROUP_ID,
        User::LIBRARIAN_USERGROUP_ID,
        User::RECEPTIONIST_USERGROUP_ID,
        User::ACCOUNTANT_USERGROUP_ID,
        User::STOCK_KEEPER_USERGROUP_ID,
        User::NON_TEACHING_USERGROUP_ID,
    ];

    /**
     * GET /api/v2/conversations
     *
     * Auth user's conversations ordered by last_message_at desc.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);

        $conversations = $user->conversations()
            ->with(['users.userprofile'])
            ->orderByDesc('last_message_at')
            ->get();

        $data = $conversations->map(function (Conversation $conversation) use ($user) {
            $other = $conversation->users->firstWhere('id', '!=', $user->id)
                ?? $conversation->users->first();
            $last  = $conversation->messages()->first(); // relation is latest-first
            $self  = $conversation->users->firstWhere('id', $user->id);
            $readAt = ($self && $self->pivot) ? $self->pivot->read_at : null;

            $unreadQuery = $conversation->messages()->where('user_id', '!=', $user->id);
            if ($readAt) {
                $unreadQuery->where('created_at', '>', $readAt);
            }

            return [
                'uuid'             => $conversation->uuid,
                'otherParticipant' => $other ? $this->participantShape($other) : null,
                'lastMessage'      => $last ? [
                    'body'      => $last->body,
                    'createdAt' => $last->created_at->toIso8601String(),
                    'isOwn'     => (int) $last->user_id === (int) $user->id,
                ] : null,
                'unreadCount'      => $unreadQuery->count(),
                'updatedAt'        => optional($conversation->last_message_at ?? $conversation->updated_at)->toIso8601String(),
            ];
        })->values();

        return response()->json(['data' => $data], 200);
    }

    /**
     * GET /api/v2/conversations/contacts
     *
     * Allowed contacts for starting a chat. Parents get the teachers of
     * their children (with childName); staff get all same-school staff
     * plus, for teachers, the parents of their current students.
     */
    public function contacts(Request $request)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);

        if ($user->isParent()) {
            $contacts = $this->teacherContactsForParent($user);
        } else {
            $contacts = $this->staffContacts($user);

            if ($user->isTeacher()) {
                $contacts = $contacts->concat($this->parentContactsForTeacher($user));
            }
        }

        return response()->json(['data' => $contacts->values()], 200);
    }

    /**
     * POST /api/v2/conversations  body {user_id}
     *
     * Find-or-create the 1:1 conversation with the target user.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $other = User::findOrFail($request->input('user_id'));

        abort_if((int) $other->id === (int) $user->id, 422, 'Cannot start a conversation with yourself.');
        abort_unless($this->canConverse($user, $other), 403, 'This conversation is not allowed.');

        $conversation = Conversation::whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->whereHas('users', function ($q) use ($other) {
                $q->where('users.id', $other->id);
            })
            ->withCount('users')
            ->get()
            ->firstWhere('users_count', 2);

        if (!$conversation) {
            $conversation = Conversation::create([
                'uuid'      => (string) Str::uuid(),
                'school_id' => $user->school_id,
            ]);
            $conversation->users()->sync([$user->id, $other->id]);
        }

        return response()->json(['uuid' => $conversation->uuid], 200);
    }

    /**
     * GET /api/v2/conversations/{conversation}
     *
     * Returns the conversation meta plus the latest 50 messages in
     * chronological (oldest-first) order.
     */
    public function show(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);
        $this->assertParticipant($conversation, $user);

        $other = $conversation->otherParticipant($user);

        // readAt on an outgoing message = the other participant's pivot
        // read marker once it has passed the message timestamp.
        $otherReadAt = null;
        if ($other) {
            $otherMembership = $conversation->users()->where('users.id', $other->id)->first();
            $otherReadAt = ($otherMembership && $otherMembership->pivot && $otherMembership->pivot->read_at)
                ? \Carbon\Carbon::parse($otherMembership->pivot->read_at)
                : null;
        }

        $messages = $conversation->messages()
            ->take(50)
            ->get()
            ->reverse()
            ->values()
            ->map(function (Message $message) use ($user, $otherReadAt) {
                $isOwn = (int) $message->user_id === (int) $user->id;

                return [
                    'id'        => $message->id,
                    'body'      => $message->body,
                    'isOwn'     => $isOwn,
                    'createdAt' => $message->created_at->toIso8601String(),
                    'readAt'    => ($isOwn && $otherReadAt && $otherReadAt->gte($message->created_at))
                        ? $otherReadAt->toIso8601String()
                        : null,
                ];
            });

        return response()->json([
            'uuid'             => $conversation->uuid,
            'otherParticipant' => $other ? $this->participantShape($other) : null,
            'messages'         => $messages,
        ], 200);
    }

    /**
     * POST /api/v2/conversations/{conversation}/messages  body {body}
     *
     * Creates the message, broadcasts it (MessageSent) and notifies the
     * other participant (NewMessageNotification ladder).
     */
    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);
        $this->assertParticipant($conversation, $user);

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body'    => $request->input('body'),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        if (!$conversation->school_id) {
            $conversation->update(['school_id' => $user->school_id]);
        }

        // The sender has by definition read up to their own message.
        $conversation->users()->updateExistingPivot($user->id, ['read_at' => now()]);

        $other = $conversation->otherParticipant($user);

        broadcast(new MessageSent($message));

        if ($other) {
            $other->notify(new NewMessageNotification($message->body, $user, $conversation));
        }

        return response()->json([
            'id'        => $message->id,
            'body'      => $message->body,
            'isOwn'     => true,
            'createdAt' => $message->created_at->toIso8601String(),
            'readAt'    => null,
        ], 201);
    }

    /**
     * POST /api/v2/conversations/{conversation}/read
     *
     * Marks all incoming messages read (pivot read_at = now).
     */
    public function markRead(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);
        $this->assertParticipant($conversation, $user);

        $readAt = now();
        $conversation->users()->updateExistingPivot($user->id, ['read_at' => $readAt]);

        return response()->json(['readAt' => $readAt->toIso8601String()], 200);
    }

    /**
     * GET /api/v2/conversations/unread-count
     *
     * Total unread incoming messages across all of the user's
     * conversations, computed off conversation_user.read_at.
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $this->assertMessagingUser($user);

        $count = Message::query()
            ->join('conversation_user as cu', function ($join) use ($user) {
                $join->on('cu.conversation_id', '=', 'conversation_messages.conversation_id')
                     ->where('cu.user_id', '=', $user->id);
            })
            ->where('conversation_messages.user_id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('cu.read_at')
                  ->orWhereColumn('conversation_messages.created_at', '>', 'cu.read_at');
            })
            ->count();

        return response()->json(['count' => $count], 200);
    }

    /**
     * Only staff and parents may use messaging; students (and any other
     * usergroup) get 403.
     *
     * @param \App\Models\User $user
     * @return void
     */
    protected function assertMessagingUser(User $user): void
    {
        abort_unless(
            in_array((int) $user->usergroup_id, self::STAFF_USERGROUPS, true) || $user->isParent(),
            403,
            'Messaging is not available for this account.'
        );
    }

    /**
     * Every endpoint on a conversation requires participation.
     *
     * @param \App\Models\Conversation $conversation
     * @param \App\Models\User $user
     * @return void
     */
    protected function assertParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->isParticipant($user->id), 403, 'You are not a participant of this conversation.');
    }

    /**
     * Product scope rule: may $user open a 1:1 with $other?
     *
     * @param \App\Models\User $user
     * @param \App\Models\User $other
     * @return bool
     */
    protected function canConverse(User $user, User $other): bool
    {
        // All conversations are school-scoped.
        if ((int) $user->school_id !== (int) $other->school_id) {
            return false;
        }

        // Student accounts have no messaging access at all.
        if ($other->isStudent()) {
            return false;
        }

        $userIsStaff  = in_array((int) $user->usergroup_id, self::STAFF_USERGROUPS, true);
        $otherIsStaff = in_array((int) $other->usergroup_id, self::STAFF_USERGROUPS, true);

        // Staff <-> staff within the same school.
        if ($userIsStaff && $otherIsStaff) {
            return true;
        }

        // Teacher <-> parent only through a current-class link.
        if ((int) $user->usergroup_id === User::TEACHER_USERGROUP_ID && $other->isParent()) {
            return $this->teacherLinkedToParent($user, $other);
        }

        if ($user->isParent() && (int) $other->usergroup_id === User::TEACHER_USERGROUP_ID) {
            return $this->teacherLinkedToParent($other, $user);
        }

        // Parent <-> parent and parent <-> non-teacher staff are forbidden.
        return false;
    }

    /**
     * A teacher may converse with a parent only if the parent is linked to
     * a student enrolled (current academic year) in a standardLink the
     * teacher teaches (class_teacher_links).
     *
     * @param \App\Models\User $teacher
     * @param \App\Models\User $parent
     * @return bool
     */
    protected function teacherLinkedToParent(User $teacher, User $parent): bool
    {
        $academicYear = SiteHelper::getAcademicYear($teacher->school_id);

        return StudentParentLink::where('parent_id', $parent->id)
            ->where('school_id', $teacher->school_id)
            ->whereIn('student_id', function ($q) use ($teacher, $academicYear) {
                $q->select('user_id')
                  ->from('student_academics')
                  ->where('academic_year_id', $academicYear->id)
                  ->whereNull('deleted_at')
                  ->whereIn('standardLink_id', function ($q2) use ($teacher, $academicYear) {
                      $q2->select('standardLink_id')
                         ->from('class_teacher_links')
                         ->where('teacher_id', $teacher->id)
                         ->where('academic_year_id', $academicYear->id)
                         ->whereNull('deleted_at');
                  });
            })
            ->exists();
    }

    /**
     * Teachers of the parent's children (current academic year).
     *
     * @param \App\Models\User $parent
     * @return \Illuminate\Support\Collection
     */
    protected function teacherContactsForParent(User $parent)
    {
        $academicYear = SiteHelper::getAcademicYear($parent->school_id);

        $links = StudentParentLink::where('parent_id', $parent->id)
            ->where('school_id', $parent->school_id)
            ->with(['userStudent.userprofile', 'userStudent.studentAcademicLatest'])
            ->get();

        return $links->flatMap(function (StudentParentLink $link) use ($academicYear) {
            $student = $link->userStudent;
            $standardLinkId = optional($student->studentAcademicLatest)->standardLink_id;

            if (!$standardLinkId) {
                return [];
            }

            $childName = $this->displayName($student);

            return Teacherlink::where('school_id', $link->school_id)
                ->where('academic_year_id', $academicYear->id)
                ->where('standardLink_id', $standardLinkId)
                ->get()
                ->map(function (Teacherlink $teacherlink) use ($childName) {
                    $teacher = $teacherlink->teacher;

                    if (!$teacher) {
                        return null;
                    }

                    return $this->participantShape($teacher) + ['childName' => $childName];
                })
                ->filter();
        })->unique('id');
    }

    /**
     * All staff of the user's school (except the user themselves).
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Support\Collection
     */
    protected function staffContacts(User $user)
    {
        return User::where('school_id', $user->school_id)
            ->whereIn('usergroup_id', self::STAFF_USERGROUPS)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get()
            ->map(function (User $contact) {
                return $this->participantShape($contact);
            });
    }

    /**
     * Parents of students in the teacher's current classes.
     *
     * @param \App\Models\User $teacher
     * @return \Illuminate\Support\Collection
     */
    protected function parentContactsForTeacher(User $teacher)
    {
        $academicYear = SiteHelper::getAcademicYear($teacher->school_id);

        $standardLinkIds = Teacherlink::where('teacher_id', $teacher->id)
            ->where('academic_year_id', $academicYear->id)
            ->pluck('standardLink_id');

        if ($standardLinkIds->isEmpty()) {
            return collect();
        }

        $links = StudentParentLink::where('school_id', $teacher->school_id)
            ->whereIn('student_id', function ($q) use ($teacher, $academicYear, $standardLinkIds) {
                $q->select('user_id')
                  ->from('student_academics')
                  ->where('school_id', $teacher->school_id)
                  ->where('academic_year_id', $academicYear->id)
                  ->whereNull('deleted_at')
                  ->whereIn('standardLink_id', $standardLinkIds);
            })
            ->with(['userParent.userprofile', 'userStudent.userprofile'])
            ->get();

        return $links->map(function (StudentParentLink $link) {
                $parentUser = $link->userParent;

                if (!$parentUser) {
                    return null;
                }

                return $this->participantShape($parentUser) + ['childName' => $this->displayName($link->userStudent)];
            })
            ->filter()
            ->unique('id');
    }

    /**
     * Contract shape for a user reference.
     *
     * @param \App\Models\User $user
     * @return array{id:int,name:string,role:string,avatarUrl?:string}
     */
    protected function participantShape(User $user): array
    {
        $shape = [
            'id'   => $user->id,
            'name' => $this->displayName($user),
            'role' => $this->roleLabel($user),
        ];

        $avatarUrl = optional($user->userprofile)->AvatarPath;
        if ($avatarUrl) {
            $shape['avatarUrl'] = $avatarUrl;
        }

        return $shape;
    }

    /**
     * Prefer the profile full name, fall back to users.name.
     *
     * @param \App\Models\User $user
     * @return string
     */
    protected function displayName(User $user): string
    {
        $full = trim(
            (string) data_get($user, 'userprofile.firstname').' '.
            (string) data_get($user, 'userprofile.lastname')
        );

        return $full !== '' ? $full : (string) $user->name;
    }

    /**
     * Map a usergroup to the contract role string.
     *
     * @param \App\Models\User $user
     * @return string
     */
    protected function roleLabel(User $user): string
    {
        return match ((int) $user->usergroup_id) {
            User::SCHOOLADMIN_USERGROUP_ID    => 'school_admin',
            User::SCHOOLSUBADMIN_USERGROUP_ID => 'school_subadmin',
            User::TEACHER_USERGROUP_ID        => 'teacher',
            User::PARENT_USERGROUP_ID         => 'parent',
            User::LIBRARIAN_USERGROUP_ID      => 'librarian',
            User::RECEPTIONIST_USERGROUP_ID   => 'receptionist',
            User::ACCOUNTANT_USERGROUP_ID     => 'accountant',
            User::STOCK_KEEPER_USERGROUP_ID   => 'stock_keeper',
            User::NON_TEACHING_USERGROUP_ID   => 'non_teaching_staff',
            default                           => 'user',
        };
    }
}
