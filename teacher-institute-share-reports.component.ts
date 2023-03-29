import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router } from '@angular/router';
import { NbDialogService } from '@nebular/theme';
import { CourseService } from 'app/services/course.service';
import { LocalDataSource } from 'ng2-smart-table';
import { ReportService } from '../../../services/report.service';
import jsPDF from 'jspdf';
import 'jspdf-autotable';
import autoTable from 'jspdf-autotable'
import { Alert } from '../../course/create-course/create-course.component';
import { TeacherService } from '../../../services/teacher.service';
import { LectureService } from '../../../services/lecture.service';
import * as XLSX from "xlsx";
// import CanvasJS from 'canvasjs';
import * as CanvasJS from 'assets/js/canvasjs.min';

@Component({
  selector: 'ngx-teacher-institute-share-reports',
  templateUrl: './teacher-institute-share-reports.component.html',
  styleUrls: ['./teacher-institute-share-reports.component.scss']
})
export class TeacherInstituteShareReportsComponent implements OnInit {

  settings = {
    actions: {
      add: false,
      edit: false,
      delete: false
    },
    columns: {
      registration_no: {
        title: 'STD Reg No.',
        type: 'string',
      },
      student_name: {
        title: 'STD Name',
        type: 'string',
      },
      payment_name: {
        title: 'Payment Name',
        type: 'string'
      },
      teacher_name: {
        title: 'Teacher Name',
        type: 'string'
      },
      amount: {
        title: 'Fee Amount',
        type: 'string',
      },
      teacher_amount: {
        title: 'Teacher Share Amount(Rs)',
        type: 'string',
      },
      institute_amount: {
        title: 'Institute Share Amount(Rs)',
        type: 'string',
      },
      fixed_institute_amount: {
        title: 'Fixed Instiute Share Amount(Rs)',
        type: 'string',
      },
      month: {
        title: 'Month',
        type: 'string',
      },
      date: {
        title: 'Payment Date',
        type: 'string',
      }
    }
  };


  source: LocalDataSource = new LocalDataSource();
  students: any[] = [];
  courses: [];

  records;
  summaryMessage;
  totalAmount;

  filter = {
    'filterOption': '',
    'courseId': '',
    'lectureId': '',
    'teacherId': '',
    'level': '',
    'reportTimeSpan': '',
    'by_month': {
      'year': '',
      'month': '',
    },
    'by_range': {
      'from_date': '',
      'to_date': ''
    }
  }

  filterOption: string;
  timeSpan: string;

  studentFeeReportAlert = new Alert();
  teachers;
  lectures;

  summary;
  countSummary;

  constructor(
    private router: Router,
    private dialogBoxService: NbDialogService,
    private reportService: ReportService,
    private courseService: CourseService,
    private teacherService: TeacherService,
    private lectureService: LectureService
  ) { }

  ngOnInit(): void {
    this.loadInitialData();
    this.getAllCourses();
    this.getAllTeachers();
    this.getAllLectures();
  }

  initData(data) {
    this.records = data;
    this.source.load(this.records);
  }

  initTotalAmount(summary) {
    this.summary = summary;
  }

  initCounts(countSummary) {
    this.countSummary = countSummary;
  }


  //alert set
  setAlert(alertStatus, alertMessage): void {
    this.studentFeeReportAlert.status = alertStatus;
    this.studentFeeReportAlert.message = alertMessage;
    setTimeout(() => { this.studentFeeReportAlert = { "status": null, "message": null } }, 4500); // fade alert
  }

  getAllCourses() {
    this.courseService.getAllCourseMediums().subscribe({
      next: (response: any) => {
        this.courses = response.data;
      },
      error: (err) => {
        console.log(err);
      }
    })
  }

  getAllTeachers() {
    this.teacherService.getAllTeachers().subscribe({
      next: (response) => {
        this.teachers = response;
        console.log(this.teachers);
      },
      error: (error) => {

      }
    })
  }

  getAllLectures() {
    this.lectureService.getAllLectures().subscribe({
      next: (response: any) => {
        this.lectures = response.data;
        console.log(this.lectures);
      },
      error: (error) => {

      }
    })
  }

  open(dialog: TemplateRef<any>) {
    this.dialogBoxService.open(dialog);
  }

  addFilters(dialog: TemplateRef<any>) {
    this.open(dialog);
  }

  navigate(event): void {
    let studentId = event.data.id;
    this.router.navigateByUrl(`/pages/student/view/${studentId}`);
  }

  selectFilterOption(filterOption) {
    console.log(this.filter);
    this.filterOption = filterOption;
  }

  selectTimeSpan(timeSpan) {
    this.timeSpan = timeSpan;
    console.log(this.filter);
  }

  loadInitialData() {
    this.reportService.getAllShareRecords().subscribe({
      next: (response: any) => {
        console.log(response);
        this.initData(response.records);
        this.initCounts(response.count);
        this.initTotalAmount(response.summary);
        this.summaryMessage = `This Reports Consists Of All Student Payments`;
        this.generateBarGraph();
      },
      error: (error) => {
        console.log(error);
      }
    })
  }

  selectCourse(courseId) {

  }

  onChangeTab(value) {
    this.generateBarGraph();
    if (value == "Graphical Reports") {
      this.generateBarGraph();
    }
  }

  allSearch(value) {
    if (value != '') {
      var newList = this.records.filter(element => {
        for (var property in element) {
          if (element.hasOwnProperty(property)) {
            if (element[property] == value) {
              return true;
            }
          }
        }
      });
      this.initData(newList);
    } else {
      this.loadInitialData();
    }
  }



  makePDF() {
    let recordsArray: any[] = [];
    for (let i = 0; i < this.records.length; i++) {
      let dataArray: any[] = [];
      const record = this.records[i];
      dataArray.push(record.registration_no);
      dataArray.push(record.student_name);
      dataArray.push(record.payment_name);
      dataArray.push(record.teacher_name);
      dataArray.push(record.amount);
      dataArray.push(record.teacher_amount);
      dataArray.push(record.institute_amount);
      dataArray.push(record.fixed_institute_amount);
      dataArray.push(record.month);
      dataArray.push(record.date);
      recordsArray.push(dataArray);
    }

    const pdf = new jsPDF();
    autoTable(pdf, {
      head: [['STD Reg No.', 'STD Name', 'Payment Name', 'Fee Amount', 'Teacher Share Amount(Rs)', 'Institute Share Amount(Rs)', 'Fixed Instiute Share Amount(Rs)', 'Month', 'Payment Date']],
      body: recordsArray,
    })

    autoTable(pdf, {
      head: [['Student Fee Amount', 'Total Teacher Amount', 'Total Institute Share Amount', 'Total Fixed Institute Amount',
        'Total Institute Revenue(Total Institute Amount + Total Fixed Institute Amount)']],
      body: [[this.summary.student_fee_amount, this.summary.total_teacher_amount, this.summary.total_institute_amount, this.summary.total_fixed_institute_amount,
      `Rs.${this.summary.total_institute_amount} + Rs.${this.summary.total_fixed_institute_amount} = Rs.${this.summary.final_total_institute_amount}`]],
      styles: { fillColor: "#43a047" },
    })
    pdf.save();
  }

  seeBarGraph(modal) {
    this.open(modal);
  }



  printReport() {
    let recordsArray: any[] = [];
    for (let i = 0; i < this.records.length; i++) {
      let dataArray: any[] = [];
      const record = this.records[i];
      dataArray.push(record.registration_no);
      dataArray.push(record.student_name);
      dataArray.push(record.payment_name);
      dataArray.push(record.teacher_name);
      dataArray.push(record.amount);
      dataArray.push(record.teacher_amount);
      dataArray.push(record.institute_amount);
      dataArray.push(record.fixed_institute_amount);
      dataArray.push(record.month);
      dataArray.push(record.date);
      recordsArray.push(dataArray);
    }

    const pdf = new jsPDF();
    autoTable(pdf, {
      head: [['STD Reg No.', 'STD Name', 'Payment Name', 'Fee Amount', 'Teacher Share Amount(Rs)', 'Institute Share Amount(Rs)', 'Fixed Instiute Share Amount(Rs)', 'Month', 'Payment Date']],
      body: recordsArray,
    })

    pdf.autoPrint();
    pdf.output('dataurlnewwindow');
  }

  submitFilter(modal, data) {
    if (this.filter.filterOption != '') {
      switch (this.filter.filterOption) {
        case 'all':
          if (this.filter.reportTimeSpan != '') {
            this.getAllShareRecords(modal);
          } else {
            this.setAlert('error', 'Please Select Report Time Span');
          }
          break;
        case 'course':
          if (this.filter.courseId != '') {
            if (this.filter.reportTimeSpan != '') {
              this.getRecordsByCourse(modal);
            } else {
              this.setAlert('error', 'Please Select Report Time Span');
            }
          } else {
            this.setAlert('error', 'Please Select A Course');
          }
          break;
        case 'lecture':
          if (this.filter.lectureId != '') {
            if (this.filter.reportTimeSpan != '') {
              this.getRecordsByLecture(modal);
            } else {
              this.setAlert('error', 'Please Select Report Time Span');
            }
          } else {
            this.setAlert('error', 'Please Select A Lecture');
          }
          break;
        case 'teacher':
          if (this.filter.teacherId != '') {
            if (this.filter.reportTimeSpan != '') {
              this.getRecordsByTeacher(modal);
            } else {
              this.setAlert('error', 'Please Select Report Time Span');
            }
          } else {
            this.setAlert('error', 'Please Select A Teacher');
          }
          break;
        case 'level':
          if (this.filter.level != '') {
            if (this.filter.reportTimeSpan != '') {
              this.getAllShareRecords(modal);
            } else {
              this.setAlert('error', 'Please Select Report Time Span');
            }
          } else {
            this.setAlert('error', 'Please Select A Level');
          }
      }

      this.generateBarGraph();
    } else {
      this.setAlert('error', 'Please Select A Filter Option');
    }
  }

  getAllShareRecords(modal) {
    switch (this.filter.reportTimeSpan) {
      case 'all_time':
        this.summaryMessage = `This Reports Consists Of All Student Payments`;
        this.reportService.getAllShareRecords().subscribe({
          next: (response: any) => {
            this.initData(response.records);
            this.initTotalAmount(response.summary);
            this.initCounts(response.count);
            modal.close();
          },
          error: (error) => {
            console.log(error);
          }
        })
        break;
      case 'by_month':
        if (this.filter.by_month.month != '' && this.filter.by_month.year != '') {
          this.summaryMessage = `This Report Consists Of All Student Payments in Year ${this.filter.by_month.year}, Month ${this.filter.by_month.month}`;
          this.reportService.getAllShareRecordsByMonth(this.filter.by_month.year, this.filter.by_month.month).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
          break;
        } else {
          this.setAlert('error', 'Please Select An Year And A Month');
        }
        break;
      case 'by_range':
        if (this.filter.by_range.from_date != '' && this.filter.by_range.to_date != '') {
          this.summaryMessage = `This Report Consists Of All Student Payments from ${this.filter.by_range.from_date} to ${this.filter.by_range.to_date}`;
          this.reportService.getAllShareRecordsByDate(this.filter.by_range.from_date, this.filter.by_range.to_date).subscribe({
            next: (response: any) => {
              console.log(response);
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
          break;
        } else {
          this.setAlert('error', 'Please Select A Date Frame');
        }
    }
  }


  getRecordsByCourse(modal) {
    switch (this.filter.reportTimeSpan) {
      case 'all_time':
        this.reportService.getAllShareRecordsByCourse(this.filter.courseId).subscribe({
          next: (response: any) => {
            this.initData(response.records);
            this.initTotalAmount(response.summary);
            this.initCounts(response.count);
            modal.close();
          },
          error: (error) => {
            console.log(error);
          }
        })
        break;
      case 'by_month':
        if (this.filter.by_month.month != '' && this.filter.by_month.year != '') {
          this.reportService.getAllShareRecordsForCourseByMonth(this.filter.courseId, this.filter.by_month.year, this.filter.by_month.month).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
          break;
        } else {
          this.setAlert('error', 'Please Select An Year And A Month');
        }
        break;
      case 'by_range':
        if (this.filter.by_range.from_date != '' && this.filter.by_range.to_date != '') {
          this.reportService.getAllShareRecordsForCourseByDate(this.filter.courseId, this.filter.by_range.from_date, this.filter.by_range.to_date).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
          break;
        } else {
          this.setAlert('error', 'Please Select A Date Frame');
        }

    }
  }


  getRecordsByTeacher(modal) {
    switch (this.filter.reportTimeSpan) {
      case 'all_time':
        this.reportService.getAllShareRecordsByTeacher(this.filter.courseId).subscribe({
          next: (response: any) => {
            this.initData(response.records);
            this.initTotalAmount(response.summary);
            this.initCounts(response.count);
            modal.close();
          },
          error: (error) => {
            console.log(error);
          }
        })
        break;
      case 'by_month':
        if (this.filter.by_month.month != '' && this.filter.by_month.year != '') {
          this.reportService.getAllShareRecordsForTeacherByMonth(this.filter.teacherId, this.filter.by_month.year, this.filter.by_month.month).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
          break;
        } else {
          this.setAlert('error', 'Please Select An Year And A Month');
        }
        break;
      case 'by_range':
        if (this.filter.by_range.from_date != '' && this.filter.by_range.to_date != '') {
          if (this.filter.by_month.month != '' && this.filter.by_month.year != '') {
            this.reportService.getAllShareRecordsForTeacherByDate(this.filter.teacherId, this.filter.by_range.from_date, this.filter.by_range.to_date).subscribe({
              next: (response: any) => {
                this.initData(response.records);
                this.initTotalAmount(response.summary);
                this.initCounts(response.count);
                modal.close();
              },
              error: (error) => {
                console.log(error);
              }
            })
            break;
          } else {
            this.setAlert('error', 'Please Select A Date Frame');
          }
        }
    }
  }

  getRecordsByLecture(modal) {
    switch (this.filter.reportTimeSpan) {
      case 'all_time':
        this.reportService.getAllShareRecordsByLecture(this.filter.lectureId).subscribe({
          next: (response: any) => {
            this.initData(response.records);
            this.initTotalAmount(response.summary);
            this.initCounts(response.count);
            modal.close();
          },
          error: (error) => {
            console.log("error");
            console.log(error);
          }
        })
        break;
      case 'by_month':
        if (this.filter.by_month.month != '' && this.filter.by_month.year != '') {
          this.reportService.getAllShareRecordsForLectureByMonth(this.filter.lectureId, this.filter.by_month.year, this.filter.by_month.month).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
        } else {
          this.setAlert('error', 'Please Select An Year And A Month');
        }
        break;
      case 'by_range':
        if (this.filter.by_range.from_date != '' && this.filter.by_range.to_date != '') {
          this.reportService.getAllShareRecordsForLectureByDate(this.filter.lectureId, this.filter.by_range.from_date, this.filter.by_range.to_date).subscribe({
            next: (response: any) => {
              this.initData(response.records);
              this.initTotalAmount(response.summary);
              this.initCounts(response.count);
              modal.close();
            },
            error: (error) => {
              console.log(error);
            }
          })
        } else {
          this.setAlert('error', 'Please Select A Date Frame');
        }
    }

  }

  generateBarGraph() {
    let chartData = [];
    for (let i = 0; i < this.countSummary.length; i++) {
      const element = this.countSummary[i];
      let obj = {
        y: element.no_of_payments,
        label: element.payment_name
      }
      chartData.push(obj);
    }
    let chart = new CanvasJS.Chart("chartContainer", {
      animationEnabled: true,
      exportEnabled: true,
      title: {
        text: "Payments Of Students"
      },
      data: [{
        type: "column",
        dataPoints: chartData
      }]
    });
    chart.render();


    let pieChartData = [];
    for (let i = 0; i < this.countSummary.length; i++) {
      const element = this.countSummary[i];
      let obj = {
        y: element.no_of_payments,
        name: element.payment_name
      }
      pieChartData.push(obj);
    }

    let pieChart = new CanvasJS.Chart("pieChartContainer", {
      theme: "light2",
      animationEnabled: true,
      exportEnabled: true,
      title: {
        text: "Payments Percentage"
      },
      data: [{
        type: "pie",
        showInLegend: true,
        toolTipContent: "<b>{name}</b>: ${y} (#percent%)",
        indexLabel: "{name} - #percent%",
        dataPoints: pieChartData
      }]
    });

    pieChart.render();
  }


}
