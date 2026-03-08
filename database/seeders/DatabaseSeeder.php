<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['phone' => '+251900000000'],
            [
                'name' => 'Admin User',
                'email' => 'admin@lmsplatform.com',
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
                'email' => 'instructor@lmsplatform.com',
                'role' => 'instructor',
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Create default categories
        $categories = [
            ['name' => 'Programming', 'slug' => 'programming', 'description' => 'Software development and coding courses'],
            ['name' => 'Design', 'slug' => 'design', 'description' => 'UI/UX and graphic design courses'],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Business and entrepreneurship courses'],
            ['name' => 'Marketing', 'slug' => 'marketing', 'description' => 'Digital marketing and growth courses'],
            ['name' => 'Data Science', 'slug' => 'data-science', 'description' => 'Data analysis and machine learning courses'],
            ['name' => 'Language', 'slug' => 'language', 'description' => 'Language learning courses'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
