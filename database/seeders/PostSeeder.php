<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use App\PostStatus;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->orderBy('id')->first();
        if ($author === null) {
            return;
        }
        $articles = [
            ['Twenty Years of God’s Faithfulness', 'Anniversary', PostStatus::Published, true],
            ['Preparing for the Church Building Dedication', 'Announcements', PostStatus::Published, true],
            ['What Prayer Can Do', 'Devotionals', PostStatus::Published, false],
            ['Community Outreach at Kwanwoma Hospital', 'Community Outreach', PostStatus::Published, false],
            ['Highlights from Prayer Galore', 'Church News', PostStatus::Draft, false],
            ['The Role of Christian Fellowship', 'Teachings', PostStatus::Scheduled, false],
            ['A Message from the Lead Pastor', 'Church News', PostStatus::Published, true],
            ['Youth and Ministry Development', 'Ministry Updates', PostStatus::Archived, false],
        ];
        $tags = Tag::query()->get();
        foreach ($articles as $index => [$title, $categoryName, $status, $featured]) {
            $post = Post::withTrashed()->updateOrCreate(
                ['slug' => str($title)->slug()->toString()],
                [
                    'author_id' => $author->id,
                    'post_category_id' => PostCategory::query()->where('name', $categoryName)->value('id'),
                    'title' => $title,
                    'excerpt' => "Discover how {$title} continues to shape the life and mission of Triumphant World Ministry.",
                    'content' => "<p>God continues to work through Triumphant World Ministry and its people.</p><h2>{$title}</h2><p>This article shares the story, lessons, and next steps for our church family.</p>",
                    'status' => $status,
                    'visibility' => 'public',
                    'is_featured' => $featured,
                    'published_at' => $status === PostStatus::Published ? now()->subDays($index + 1) : null,
                    'scheduled_for' => $status === PostStatus::Scheduled ? now()->addDays(3) : null,
                    'archived_at' => $status === PostStatus::Archived ? now()->subDay() : null,
                    'deleted_at' => null,
                ],
            );
            $post->tags()->sync($tags->take(2 + ($index % 2))->modelKeys());
        }
    }
}
