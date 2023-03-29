<?php


namespace App\Services\StudentPayments;


use App\MonthlyPayment;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

class TeacherInstituteShareReport extends Service
{


    //all time
    public function totalStudentFees()
    {
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid'");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");


        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }



    public function totalStudentFeesByMonth($year, $month) {
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid'
                            AND year='$year' AND month='$month'");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
       sum(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");


        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeesByDate($fromDate, $toDate)
    {
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid'
                            AND (payment_date BETWEEN '$fromDate' AND '$toDate')");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }




    //course
    public function totalStudentFeeForCourse($courseId){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id FROM lectures WHERE course_medium_id=$courseId)))");


        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForCourseByMonth($courseId, $year, $month){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND year='$year' AND month='$month' AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id FROM lectures WHERE course_medium_id=$courseId)))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForCourseByDate($courseId, $from_date, $to_date){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND (payment_date BETWEEN '$from_date' AND '$to_date') AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id FROM lectures WHERE course_medium_id=$courseId)))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }




    //teacher
    public function totalStudentFeeForTeacher($teacherId){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND
                              student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id from lectures WHERE teacher_id=$teacherId)))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForTeacherByMonth($teacherId, $year, $month){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND year='$year' AND month='$month' AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id from lectures WHERE teacher_id=$teacherId)))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForTeacherByDate($teacherId, $from_date, $to_date){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND (payment_date BETWEEN '$from_date' AND '$to_date') AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id IN
                            (SELECT id from lectures WHERE teacher_id=$teacherId)))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }



    //lecture
    public function totalStudentFeeForLecture($lectureId){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE monthly_payments.status='paid' AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id=$lectureId))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForLectureByMonth($lectureId, $year, $month){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND year='$year' AND month='$month' AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id=$lectureId))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }

    public function totalStudentFeeForLectureByDate($lectureId, $from_date, $to_date){
        DB::statement("CREATE OR REPLACE VIEW fee_records AS
                            SELECT * FROM monthly_payments WHERE status='paid' AND (payment_date BETWEEN '$from_date' AND '$to_date') AND
                             student_payment_id IN
                            (SELECT id FROM student__payments WHERE payment_id IN
                            (SELECT id FROM payments WHERE lecture_id=$lectureId))");

        DB::statement("CREATE or REPLACE view student_teacher_share AS SELECT fee_records.id as monthly_payment_id,students.registration_no,
                            students.name as student_name, student__payments.payment_type as mode,
                            payments.name as payment_name, fee_records.amount, teacher_institute_shares.teacher_amount,
                            teachers.name as teacher_name, teacher_institute_shares.institute_amount, payments.fixed_institute_amount,
                            CONCAT(fee_records.year, ' ', fee_records.month) AS month, fee_records.month as only_month,
                             fee_records.year as only_year, fee_records.payment_date as date FROM fee_records
                            INNER JOIN students ON fee_records.student_id=students.id
                            INNER JOIN student__payments ON fee_records.student_payment_id=student__payments.id
                            INNER JOIN payments ON student__payments.payment_id=payments.id INNER JOIN teacher_institute_shares
                            ON teacher_institute_shares.monthly_payment_id=fee_records.id INNER JOIN teachers ON teachers.id=teacher_institute_shares.teacher_id
                            ORDER BY fee_records.payment_date");

        $records = DB::select("SELECT * FROM student_teacher_share");

        $summary = DB::select("select total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount,
                        SUM(total_institute_amount+total_fixed_institute_amount) as final_total_institute_amount FROM (SELECT SUM(teacher_amount) total_teacher_amount,
                        SUM(institute_amount) total_institute_amount,
                        SUM(fixed_institute_amount) total_fixed_institute_amount,
                        SUM(amount) as student_fee_amount FROM student_teacher_share) B
                group by total_teacher_amount, total_institute_amount,total_fixed_institute_amount,student_fee_amount");

        $count = DB::select("SELECT COUNT(student_teacher_share.monthly_payment_id) as no_of_payments, payment_name
                                    FROM `student_teacher_share` GROUP BY payment_name");

        $result = [
            "records"=>$records,
            "summary"=>$summary[0],
            "count"=>$count
        ];

        return $result;
    }
}