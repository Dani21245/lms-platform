<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['phone' => '+251900000000'],
            [
                'name' => 'Admin',
                'email' => 'admin@lms.example',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Create sample instructor
        User::firstOrCreate(
            ['phone' => '+251911111111'],
            [
                'name' => 'Sample Instructor',
                'email' => 'instructor@lms.example',
                'password' => Hash::make('password'),
                'role' => 'instructor',
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Create sample student
        User::firstOrCreate(
            ['phone' => '+251922222222'],
            [
                'name' => 'Sample Student',
                'email' => 'student@lms.example',
                'password' => Hash::make('password'),
                'role' => 'student',
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Create categories
        $categories = [
            ['name' => 'Programming', 'slug' => 'programming', 'description' => 'Software development and programming courses', 'sort_order' => 1],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Business and entrepreneurship courses', 'sort_order' => 2],
            ['name' => 'Design', 'slug' => 'design', 'description' => 'UI/UX and graphic design courses', 'sort_order' => 3],
            ['name' => 'Marketing', 'slug' => 'marketing', 'description' => 'Digital marketing and SEO courses', 'sort_order' => 4],
            ['name' => 'Language', 'slug' => 'language', 'description' => 'Language learning courses', 'sort_order' => 5],
            ['name' => 'Science', 'slug' => 'science', 'description' => 'Science and mathematics courses', 'sort_order' => 6],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
