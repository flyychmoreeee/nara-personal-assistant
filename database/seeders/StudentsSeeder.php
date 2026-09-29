<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = [
            ['attendance_number' => 1, 'name' => 'Amanda Evania Putri', 'nickname' => 'Manda'],
            ['attendance_number' => 2, 'name' => 'Andika Bagus Pratama', 'nickname' => 'Andika'],
            ['attendance_number' => 3, 'name' => 'Arsenio Asyraf Attala', 'nickname' => 'Nio'],
            ['attendance_number' => 4, 'name' => 'Aura Amalya Putri', 'nickname' => 'Aura'],
            ['attendance_number' => 5, 'name' => 'Bagus Aji Satria', 'nickname' => 'Bagus'],
            ['attendance_number' => 6, 'name' => 'Brandon Rayhan Ringgau', 'nickname' => 'Brandon'],
            ['attendance_number' => 7, 'name' => 'Daffa Paundra Gandi', 'nickname' => 'Popon'],
            ['attendance_number' => 8, 'name' => 'Dzaky Ananda Restiawan', 'nickname' => 'Dzaky'],
            ['attendance_number' => 9, 'name' => 'Faatikah Alba Roah', 'nickname' => 'Alba'],
            ['attendance_number' => 10, 'name' => 'Fardan Rasyah Islami', 'nickname' => 'Fardan'],
            ['attendance_number' => 11, 'name' => 'Fatir Fauziam', 'nickname' => 'Fatir'],
            ['attendance_number' => 12, 'name' => 'Garneta Ghania Fazila', 'nickname' => 'Neta'],
            ['attendance_number' => 13, 'name' => 'Hafizh Adriansyah Nabil', 'nickname' => 'Hafizh'],
            ['attendance_number' => 14, 'name' => 'Hilmy Nurani Khoirunnisa’', 'nickname' => 'Hilmy'],
            ['attendance_number' => 15, 'name' => 'Khalishah Azzahra Dellanggra', 'nickname' => 'Dera'],
            ['attendance_number' => 16, 'name' => 'Kharisa Nurushofi Muhajir', 'nickname' => 'Kharisa'],
            ['attendance_number' => 17, 'name' => 'M. Nauril Azzakiy', 'nickname' => 'Nauril'],
            ['attendance_number' => 18, 'name' => 'Melvian Azril Hamzah', 'nickname' => 'Vian'],
            ['attendance_number' => 19, 'name' => 'Muhamad Rasya Zildan Alifansyah', 'nickname' => 'Rasya'],
            ['attendance_number' => 20, 'name' => 'Muhammad Arya Pranaja Hadi', 'nickname' => 'Arya'],
            ['attendance_number' => 21, 'name' => 'Muhammad Hilman Akma Hafizuddin', 'nickname' => 'Hilman'],
            ['attendance_number' => 22, 'name' => 'Nadyn Octaviani Putri', 'nickname' => 'Nadyn'],
            ['attendance_number' => 23, 'name' => 'Nasya Maiza Raissatidi', 'nickname' => 'Nasya'],
            ['attendance_number' => 24, 'name' => 'Raziq Awalia Mawarda', 'nickname' => 'Raziq'],
            ['attendance_number' => 25, 'name' => 'Rezky Naufal Ramadhan', 'nickname' => 'Rezky'],
            ['attendance_number' => 26, 'name' => 'Shafira Fadila Anggita Rianto', 'nickname' => 'Shafira'],
            ['attendance_number' => 27, 'name' => 'Siti Azzarah Aulia Putri', 'nickname' => 'Zahra'],
            ['attendance_number' => 28, 'name' => 'Talitha Aurelia Salsabila', 'nickname' => 'Talitha'],
            ['attendance_number' => 29, 'name' => 'Thoriq Qowiem Assabiqi', 'nickname' => 'Thoriq'],
            ['attendance_number' => 30, 'name' => 'Tsaqifah Zayyan Suha Putri', 'nickname' => 'Zayyan'],
        ];

        foreach ($students as $student) {
            Student::updateOrCreate(
                ['attendance_number' => $student['attendance_number']],
                [
                    'name' => $student['name'],
                    'nickname' => $student['nickname'],
                    'is_active' => true,
                ]
            );
        }
    }
}
