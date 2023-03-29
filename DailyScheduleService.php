<?php

namespace App\Services\DailySchedule;
use App\Attendance;
use App\DailySchedule;
use App\Lecture;
use App\Mail\ResetPasswordCode;
use App\Mail\ScheduleNoti;
use App\Mail\ScheduleNotification;
use App\Room;
use App\ScheduleNotifications;
use App\Services\Service;
use App\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DailyScheduleService extends Service
{
    public function createOneTimeSchedule($requestBody)
    {
        $room = Room::findOrFail($requestBody['room_id']);
        $lectureId = $requestBody['lecture_id'];

        $noOfStudentsInLecture = DB::select("SELECT COUNT(student_id) as student_count FROM lecture_student
                            WHERE lecture_id=$lectureId");
        $studentCount = $noOfStudentsInLecture[0];

        //greater than no of seats
        if ($studentCount->student_count > $room->no_of_seats){
            return $this->errorResponse("This $room->name with capacity $room->no_of_seats Cannot Allocate $studentCount->student_count Students",400);
        }

        $date = $requestBody['date'];
        $teacher = Lecture::findOrFail($requestBody['lecture_id'])->teacher;

        $existingTeacherSchedules = DB::select("SELECT * FROM daily_schedules WHERE date='$date'
                             AND lecture_id IN (SELECT id FROM lectures WHERE teacher_id=$teacher->id)");

        $teacherStartTimeMatch = false;
        $teacherEndTimeMatch = false;
        if (count($existingTeacherSchedules)!=0){
            foreach ($existingTeacherSchedules as $teacherSchedule)
            {
                $teacherStartTimeMatch = $this->TimeIsBetweenTwoTimes($teacherSchedule->start_time,
                    $teacherSchedule->end_time, $requestBody['start_time']);

                $teacherEndTimeMatch = $this->TimeIsBetweenTwoTimes($teacherSchedule->start_time,
                    $teacherSchedule->end_time, $requestBody['end_time']);
            }
        }

        if ($teacherStartTimeMatch or $teacherEndTimeMatch) {
            return $this->errorResponse("This Teacher Has Another Lecture at this Schedule Slot",400);
        }

        $similarSchedules = DB::table('daily_schedules')
            ->where([
                ['date',$requestBody['date']],
                ['room_id',$requestBody['room_id']]
            ])->get();

        $startTimeMatch = false;
        $endTimeMatch = false;
        if ($similarSchedules != null) {
            foreach ($similarSchedules as $similarSchedule) {
                $startTimeMatch = $this->TimeIsBetweenTwoTimes($similarSchedule->start_time,
                    $similarSchedule->end_time, $requestBody['start_time']);

                $endTimeMatch = $this->TimeIsBetweenTwoTimes($similarSchedule->start_time,
                    $similarSchedule->end_time, $requestBody['end_time']);
            }
        }

        if (!($startTimeMatch or $endTimeMatch))
        {
            $dailySchedule = DailySchedule::create($requestBody);

            $lecture     = $dailySchedule->lecture;
            $lectureName = $lecture->name;
            $roomName    = $dailySchedule->room->name;
            $date        = $dailySchedule->date;
            $startTime   = $dailySchedule->start_time;
            $endTime     = $dailySchedule->end_time;


            $message = "A new lecture schedule was created for $lectureName
                        Room - $roomName.
                        Date - $date.
                        From - $startTime.
                        To   - $endTime.";

            $notification = new ScheduleNotifications();
            $notification->daily_schedule_id = $dailySchedule->id;
            $notification->action = "create";
            $notification->save();

            Mail::to($teacher->email)->send(new ScheduleNoti($message));

            $studentEmails = DB::select("SELECT email from students where id IN
                            (SELECT student_id from lecture_student where lecture_id IN
                            (SELECT lecture_id from daily_schedules where id=$dailySchedule->id))");

            foreach ($studentEmails as $email){
                Mail::to($email)->send(new ScheduleNoti($message));
            }

            return $this->showOne($dailySchedule);
        } else {
            return $this->errorResponse("This Schedule Time Slot Is Already Occupied",400);
        }
    }

    public function findByDate($date)
    {
        $todayPHP = date("Y-m-d");
        $today = date('Y-m-d',strtotime($todayPHP));
        DB::update("UPDATE daily_schedules set status='completed' WHERE date<'$today'");

        $formattedDate = Carbon::parse($date)->format('Y-m-d');
        $dailySchedules = DailySchedule::where('date', $formattedDate)
            ->with('lecture.teacher')
            ->get();

        foreach ($dailySchedules as $dailySchedule) {
            $start_time_12 = date('h:i A ', strtotime($dailySchedule->start_time));
            $end_time_12  = date('h:i A ', strtotime($dailySchedule->end_time));
            $dailySchedule->{"start_time_12"} = $start_time_12;
            $dailySchedule->{"end_time_12"} = $end_time_12;
        }
        return $dailySchedules;
    }

    public function findByDateAndStudent($date, $studentId) {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');
        $student = Student::findOrFail($studentId);
        $lecturesOfStudent = $student->lectures;
        $lectureIds = [];

        foreach ($lecturesOfStudent as $lecture) {
            array_push($lectureIds, $lecture->id);
        }

        $dailySchedules = DailySchedule::where('date', $formattedDate)
            ->whereIn('lecture_id', $lectureIds)
            ->with('lecture.teacher')
            ->get();

        return $dailySchedules;
    }

    public function findScheduleForDay($date, $startTime, $endTime, $roomId) {


        $allSchedules = DB::table('daily_schedules')
            ->where([
                ['date',$date],
                ['room_id',$roomId]
            ])->get();
    }

    public function updateSchedule($requestBody, DailySchedule $dailySchedule) {

        if ($dailySchedule->status != 'completed') {

            $room = Room::findOrFail($requestBody['room_id']);
            $lectureId = $requestBody['lecture_id'];

            $noOfStudentsInLecture = DB::select("SELECT COUNT(student_id) as student_count FROM lecture_student
                            WHERE lecture_id=$lectureId");
            $studentCount = $noOfStudentsInLecture[0];

            if ($studentCount->student_count>$room->no_of_seats){
                return $this->errorResponse("This $room->name with capacity $room->no_of_seats Cannot Allocate $studentCount->student_count Students",400);
            }


            $date = $requestBody['date'];
            $teacher = Lecture::findOrFail($requestBody['lecture_id'])->teacher;

            $existingTeacherSchedules = DB::select("SELECT * FROM daily_schedules WHERE id!=$dailySchedule->id AND date='$date'
                             AND lecture_id IN (SELECT id FROM lectures WHERE teacher_id=$teacher->id)");

            $teacherStartTimeMatch = false;
            $teacherEndTimeMatch = false;
            if (count($existingTeacherSchedules) != 0) {
                foreach ($existingTeacherSchedules as $teacherSchedule) {
                    $teacherStartTimeMatch = $this->TimeIsBetweenTwoTimes($teacherSchedule->start_time,
                        $teacherSchedule->end_time, $requestBody['start_time']);

                    $teacherEndTimeMatch = $this->TimeIsBetweenTwoTimes($teacherSchedule->start_time,
                        $teacherSchedule->end_time, $requestBody['end_time']);
                }
            }

            if ($teacherStartTimeMatch or $teacherEndTimeMatch) {
                return $this->errorResponse("This teacher Has Another lecture at this schedule slot", 400);
            }

            $similarSchedules = DB::table('daily_schedules')
                ->where([
                    ['id', '!=', $dailySchedule->id],
                    ['date', $requestBody['date']],
                    ['room_id', $requestBody['room_id']]
                ])->get();

            $startTimeMatch = false;
            $endTimeMatch = false;

            if ($similarSchedules != null) {
                foreach ($similarSchedules as $similarSchedule) {
                    $startTimeMatch = $this->TimeIsBetweenTwoTimes($similarSchedule->start_time,
                        $similarSchedule->end_time, $requestBody['start_time']);

                    $endTimeMatch = $this->TimeIsBetweenTwoTimes($similarSchedule->start_time,
                        $similarSchedule->end_time, $requestBody['end_time']);
                }
            }

            if (!($startTimeMatch or $endTimeMatch)) {
                $oldDate = $dailySchedule->date;
                DB::update(
                    'update daily_schedules
                      set date = ?,
                      start_time = ?,
                      end_time = ?,
                      room_id = ?
                      where id = ?',
                    [$requestBody->date, $requestBody->start_time, $requestBody->end_time, $requestBody->room_id,
                        $dailySchedule->id]);

                $lecture = $dailySchedule->lecture;
                $dailyScheduleUpdated = DailySchedule::findOrFail($dailySchedule->id);

                $roomName = $dailyScheduleUpdated->room->name;
                $message = "The $lecture->name lecture on $oldDate, was updated to
                        Room - $roomName.
                        Date - $dailyScheduleUpdated->date.
                        From - $dailyScheduleUpdated->start_time.
                        To   - $dailyScheduleUpdated->end_time.";

                $messages="sasa";
                $notification = new ScheduleNotifications();
                $notification->daily_schedule_id = $dailySchedule->id;
                $notification->old_date = $oldDate;
                $notification->action = "update";
                $notification->save();

                Mail::to($teacher->email)->send(new ScheduleNoti($message));

                $studentEmails = DB::select("SELECT email from students where id IN
                            (SELECT student_id from lecture_student where lecture_id IN
                            (SELECT lecture_id from daily_schedules where id=$dailySchedule->id))");

                foreach ($studentEmails as $email) {
                    Mail::to($email)->send(new ScheduleNoti($message));
                }
                return $this->showOne($dailySchedule);
            } else {
                return $this->errorResponse("This Schedule Time Slot Is Already Occupied", 400);
            }
        } else {
            return response()->json("Cannot Update Completed Schedule", 400);
        }
    }


    public function findByDateAndLecture($date, $lectureId, $studentId) {
        $dailySchedules = DailySchedule::where([
            ['date', $date],
            ['lecture_id', $lectureId]
        ])->with('room')->get();

        foreach ($dailySchedules as $dailySchedule) {
            $dailySchedule->{"attendance"} = null;
        }
        foreach ($dailySchedules as $dailySchedule) {
            $attendance = Attendance::where([
                ['daily_schedule_id', $dailySchedule->id],
                ['student_id', $studentId]
            ])->get()->first();

            if ($attendance) {
                $dailySchedule->attendance = $attendance->attendance_status;
            }
        }

        return $dailySchedules;
    }

}