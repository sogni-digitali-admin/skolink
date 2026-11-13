<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api\Teacher;

use App\Http\Resources\API\Teacher\Notice as NoticeSchoolResource;
use App\Http\Resources\Notice as NoticeResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NoticeBoard;
use App\Models\StandardLink;
use App\Models\Teacherlink;
use App\Helpers\SiteHelper;
use App\Models\User;

class NoticeBoardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function indexSchool()
    {
        //
        $academic_year = SiteHelper::getAcademicYear(Auth::user()->school_id);
        $notice = NoticeBoard::where([['school_id',Auth::user()->school_id],['standardLink_id',null]])->where('expire_date','>=',date('Y-m-d'))->where('status',1)->get();
        $noticelist = NoticeSchoolResource::collection($notice);
        
        return response()->json([
            'success'   =>  true,
            'message'   =>  'Notice List',
            'type'      =>  'school',
            'data'      =>  $noticelist
        ],200);
    }

    public function showNotices($teacher_id)
    {
        //
        $standardLinks=[];
        $teacher = User::where('id',$teacher_id)->first();

        if(count($teacher->teacherlink)>0){
        $standardLinks=$teacher->teacherlink->pluck('standardLink_id')->toArray();
        }

        $notice = NoticeBoard::whereIn('standardLink_id',$standardLinks)->where('expire_date','>=',date('Y-m-d'))->where('status',1)->get();

       // $notice = NoticeBoard::where([['school_id',Auth::user()->school_id],['standardLink_id',null]])->where('expire_date','>=',date('Y-m-d'))->where('status',1)->get();
        $noticelist = NoticeSchoolResource::collection($notice);
        
        return response()->json([
            'success'   =>  true,
            'message'   =>  'Notice List',
            'type'      =>  'school',
            'data'      =>  $noticelist
        ],200);
    }


    /**
     * Store a newly created class notice (teacher composer in the PWA).
     *
     * PWA contract: POST /api/teacher/notice/add with
     * {standardLink_id, title, description}. The teacher must teach (or be
     * class teacher of) the target class.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'standardLink_id' => 'required|integer',
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'expire_date'     => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $teacher = Auth::user();

        $teaches = Teacherlink::where('teacher_id', $teacher->id)
            ->where('standardLink_id', $request->standardLink_id)
            ->exists();

        $isClassTeacher = StandardLink::where('id', $request->standardLink_id)
            ->where('class_teacher_id', $teacher->id)
            ->exists();

        if (! $teaches && ! $isClassTeacher) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'You are not assigned to this class.',
            ], 403);
        }

        $school_id     = $teacher->school_id;
        $academic_year = SiteHelper::getAcademicYear($school_id);

        if (! $academic_year) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'No active academic year.',
            ], 422);
        }

        $notice = new NoticeBoard;

        $notice->school_id        = $school_id;
        $notice->academic_year_id = $academic_year->id;
        $notice->standardLink_id  = $request->standardLink_id;
        $notice->type             = 'class';
        $notice->title            = $request->title;
        $notice->description      = $request->description;
        $notice->publish_date     = date('Y-m-d H:i:s');
        $notice->expire_date      = $request->expire_date
            ? date('Y-m-d H:i:s', strtotime($request->expire_date))
            : date('Y-m-d H:i:s', strtotime('+30 days'));
        $notice->status           = $notice->expire_date >= date('Y-m-d H:i:s') ? 1 : 0;
        $notice->save();

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Notice Added',
            'data'      =>  new NoticeSchoolResource($notice),
        ], 201);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function expiredSchool()
    {
        //
        $academic_year = SiteHelper::getAcademicYear(Auth::user()->school_id);
        $notice = NoticeBoard::where([['school_id',Auth::user()->school_id],['standardLink_id',null]])->where('expire_date','<=',date('Y-m-d'))->where('status',0)->get();
        $noticelist = NoticeSchoolResource::collection($notice);
        
        return response()->json([
            'success'   =>  true,
            'message'   =>  'Expired Notice List',
            'type'      =>  'school',
            'data'      =>  $noticelist
        ],200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $notice = NoticeBoard::where('id',$id)->where('type','==','teacher')->get();
        $noticelist = NoticeSchoolResource::collection($notice);
        
        return response()->json([
            'success'   =>  true,
            'message'   =>  'Show Notice',
            'data'      =>  $noticelist
        ],200);
    }
}
