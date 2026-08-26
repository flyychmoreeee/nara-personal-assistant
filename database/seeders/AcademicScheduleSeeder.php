<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CoursePic;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AcademicScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Active Semester
        $semester = Semester::updateOrCreate(
            ['name' => '2026/2027 Ganjil (Kelas 1E)'],
            ['is_active' => true]
        );

        // 2. Create Lecturers
        $lecturersData = [
            [
                'name' => 'Ir. Rudy Ariyanto, S.T., M.Cs.',
                'gender' => 'male',
                'phone_number' => '08123399764',
                // fix
            ],
            [
                'name' => 'Vivin Ayu Lestari, S.Pd., M.Kom.',
                'gender' => 'female',
                'phone_number' => '082143964396',
                // fix
            ],
            [
                'name' => 'Hendra Pradibta, S.E., M.Sc.',
                'gender' => 'male',
                'phone_number' => '081252537493',
                // fix
            ],
            [
                'name' => 'Ria Lusiyani, S.Pd., M.A.',
                'gender' => 'female',
                'phone_number' => '082134810271',
                // fix
            ],
            [
                'name' => 'Astrifidha Rahma Amalia, S.Pd., M.Pd.',
                'gender' => 'female',
                'phone_number' => '085850273371',
                // fix
            ],
            [
                'name' => 'Adevian Fairuz Pratama, S.S.T, M.Eng.',
                'gender' => 'male',
                'phone_number' => '081333156702',
                // fix
            ],
            [
                'name' => 'Zulmy Faqihuddin Putera, S.Pd., M.Pd.',
                'gender' => 'male',
                'phone_number' => '082234463936',
                // fix
            ],
            [
                'name' => 'Retno Damayanti, S.Pd., M.T.',
                'gender' => 'female',
                'phone_number' => '081231661779',
                // fix
            ],
        ];

        $lecturers = [];
        foreach ($lecturersData as $data) {
            $lecturers[$data['name']] = Lecturer::updateOrCreate(
                ['name' => $data['name']],
                ['gender' => $data['gender'], 'phone_number' => $data['phone_number']]
            );
        }

        // 3. Create Courses
        $coursesData = [
            ['code' => 'SIB261001', 'name' => 'Agama', 'credits' => 2, 'hours' => 2],
            ['code' => 'SIB261002', 'name' => 'Bahasa Indonesia', 'credits' => 2, 'hours' => 3],
            ['code' => 'SIB261003', 'name' => 'Bahasa Inggris Dasar', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261004', 'name' => 'Literasi Digital dan Teknologi', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261005', 'name' => 'Critical Thinking and Problem Solving', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261006', 'name' => 'Pengantar Akuntansi, Manajemen, dan Bisnis', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261007', 'name' => 'Matematika Dasar', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261008', 'name' => 'Dasar Pemrograman', 'credits' => 2, 'hours' => 4],
            ['code' => 'SIB261009', 'name' => 'Praktikum Dasar Pemrograman', 'credits' => 3, 'hours' => 6],
        ];

        $courses = [];
        foreach ($coursesData as $c) {
            $courses[$c['code']] = Course::updateOrCreate(
                ['code' => $c['code']],
                [
                    'semester_id' => $semester->id,
                    'name' => $c['name'],
                    'credits' => $c['credits'],
                    'hours' => $c['hours'],
                ]
            );
        }

        // 4. Create Course PICs (Penanggung Jawab Matkul)
        $picsData = [
            // SIB261001 - Agama
            'SIB261001' => ['name' => 'Fardan', 'gender' => 'male', 'phone_number' => '089654583010'],

            // SIB261002 - Bahasa Indonesia
            'SIB261002' => ['name' => 'Alba', 'gender' => 'female', 'phone_number' => '081330948140'],

            // SIB261003 - Bahasa Inggris Dasar
            'SIB261003' => ['name' => 'Karisa', 'gender' => 'female', 'phone_number' => '089660730505'],

            // SIB261004 - Literasi Digital dan Teknologi
            'SIB261004' => ['name' => 'Brandone', 'gender' => 'male', 'phone_number' => '083165359712'],

            // SIB261005 - Critical Thinking and Problem Solving
            'SIB261005' => ['name' => 'Talitha', 'gender' => 'female', 'phone_number' => '089522336262'],

            // SIB261006 - Pengantar Akuntansi, Manajemen, dan Bisnis
            'SIB261006' => ['name' => 'Fatir', 'gender' => 'male', 'phone_number' => '085148084754'],

            // SIB261007 - Matematika Dasar
            'SIB261007' => ['name' => 'Rezky', 'gender' => 'male', 'phone_number' => '087842224421'],

            // SIB261008 - Dasar Pemrograman
            'SIB261008' => ['name' => 'Thoriq', 'gender' => 'male', 'phone_number' => '081197092809'],

            // SIB261009 - Praktikum Dasar Pemrograman
            'SIB261009' => ['name' => 'Hilmi', 'gender' => 'male', 'phone_number' => '085816046325'],
        ];

        $pics = [];
        foreach ($picsData as $code => $p) {
            $pics[$code] = CoursePic::updateOrCreate(
                ['phone_number' => $p['phone_number']],
                ['name' => $p['name'], 'gender' => $p['gender']]
            );
        }

        // 5. Create Schedules 
        $schedulesData = [
            [
                'course_code' => 'SIB261005',
                'lecturer_name' => 'Ir. Rudy Ariyanto, S.T., M.Cs.',
                'day' => 'monday',
                'start_time' => '07:00:00',
                'end_time' => '10:35:00',
                'room' => 'RT-01',
            ],
            [
                'course_code' => 'SIB261008',
                'lecturer_name' => 'Vivin Ayu Lestari, S.Pd., M.Kom.',
                'day' => 'monday',
                'start_time' => '10:35:00',
                'end_time' => '14:25:00',
                'room' => 'Lab TI 1',
            ],
            [
                'course_code' => 'SIB261006',
                'lecturer_name' => 'Hendra Pradibta, S.E., M.Sc.',
                'day' => 'tuesday',
                'start_time' => '07:00:00',
                'end_time' => '10:35:00',
                'room' => 'RT-02',
            ],
            [
                'course_code' => 'SIB261003',
                'lecturer_name' => 'Ria Lusiyani, S.Pd., M.A.',
                'day' => 'tuesday',
                'start_time' => '10:35:00',
                'end_time' => '13:35:00',
                'room' => 'RT-03',
            ],
            [
                'course_code' => 'SIB261001',
                'lecturer_name' => 'Astrifidha Rahma Amalia, S.Pd., M.Pd.',
                'day' => 'tuesday',
                'start_time' => '14:25:00',
                'end_time' => '16:20:00',
                'room' => 'RT-01',
            ],
            [
                'course_code' => 'SIB261007',
                'lecturer_name' => 'Adevian Fairuz Pratama, S.S.T, M.Eng.',
                'day' => 'wednesday',
                'start_time' => '07:00:00',
                'end_time' => '10:35:00',
                'room' => 'RT-04',
            ],
            [
                'course_code' => 'SIB261002',
                'lecturer_name' => 'Zulmy Faqihuddin Putera, S.Pd., M.Pd.',
                'day' => 'wednesday',
                'start_time' => '10:35:00',
                'end_time' => '14:25:00',
                'room' => 'RT-02',
            ],
            [
                'course_code' => 'SIB261009',
                'lecturer_name' => 'Vivin Ayu Lestari, S.Pd., M.Kom.',
                'day' => 'thursday',
                'start_time' => '07:00:00',
                'end_time' => '12:15:00',
                'room' => 'Lab TI 2',
            ],
            [
                'course_code' => 'SIB261004',
                'lecturer_name' => 'Retno Damayanti, S.Pd., M.T.',
                'day' => 'friday',
                'start_time' => '12:45:00',
                'end_time' => '16:20:00',
                'room' => 'RT-05',
            ],
        ];

        foreach ($schedulesData as $s) {
            Schedule::updateOrCreate(
                [
                    'course_id' => $courses[$s['course_code']]->id,
                    'day' => $s['day'],
                    'start_time' => $s['start_time'],
                ],
                [
                    'lecturer_id' => $lecturers[$s['lecturer_name']]->id,
                    'course_pic_id' => $pics[$s['course_code']]->id,
                    'end_time' => $s['end_time'],
                    'room' => $s['room'],
                    'is_active' => true,
                ]
            );
        }
    }
}
