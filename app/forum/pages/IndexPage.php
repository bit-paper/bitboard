<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\RelativeTime;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\Database;

use App\Interfaces\PageInterface;

use App\Forum\Structs\CategoryStruct;
use App\Forum\Structs\ForumStruct;
use App\Forum\Structs\SubForumStruct;

use App\Forum\Controllers\ForumController;

class IndexPage extends PageBase implements PageInterface
{
    private $lastAccount;
    private int $totalPosts;
    private int $totalThreads;
    private int $totalUsers;

    private string $cats = '';

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = $data->forum_description;

        // This is here only for index page.
        $this->Do();
    }

    private function FetchLastAccount()
    {
        $this->lastAccount = $this->database->Query('SELECT a.*, r.rank_format FROM bit_accounts AS a JOIN bit_ranks AS r ON a.rank_id = r.id ORDER BY a.reg_date DESC LIMIT 1')->FetchArray();
        $this->lastAccount['avatar'] = AvatarUtils::GetPath($this->lastAccount['avatar']);
        $this->lastAccount['formatted_username'] = UsernameUtils::Format($this->lastAccount['rank_format'], $this->lastAccount['username']);
    }

    private function FetchStats()
    {
        $this->totalPosts = $this->database->Query('SELECT * FROM bit_posts')->NumRows();
        $this->totalThreads = $this->database->Query('SELECT * FROM bit_threads')->NumRows();
        $this->totalUsers = $this->database->Query('SELECT * FROM bit_accounts')->NumRows();
    }

    private function FetchCategories()
    {
        $data = $this->database->Query('SELECT c.id AS category_id, c.category_name, c.category_icon, c.category_desc, c.category_position, f.id AS forum_id, f.forum_name, f.forum_icon, f.forum_desc, f.forum_position, f.is_locked AS forum_locked, f.category_id AS forum_catid, COUNT(DISTINCT t.id) AS thread_count, COUNT(p.id) AS post_count, s.id AS subforum_id, s.subforum_name, s.subforum_desc, s.is_locked AS subforum_locked, s.forum_id AS subforum_forumid FROM bit_categories AS c LEFT JOIN bit_forums AS f ON f.category_id = c.id LEFT JOIN bit_threads AS t ON t.forum_id = f.id LEFT JOIN bit_posts AS p ON p.thread_id = t.id LEFT JOIN bit_subforums AS s ON s.forum_id = f.id GROUP BY c.id, f.id, s.id ORDER BY c.category_position ASC, f.forum_position ASC')->FetchAll();

        $builded_data = array();
        $dCount = count($data);

        for ($i = 0; $i < $dCount; $i++) 
        {
            $d = $data[$i];

            if(!isset($builded_data[$d['category_id']]))
            {
                $categoryData = [
                    'id' => $d['category_id'],
                    'category_name' => $d['category_name'],
                    'category_icon' => $d['category_icon'],
                    'category_desc' => $d['category_desc'],
                    'category_position' => $d['category_position'],
                ];
                
                $category = new CategoryStruct($categoryData);
                $builded_data[$d['category_id']] = $category;
            }

            if($d['forum_id'] > 0 && $d['category_id'] == $d['forum_catid'])
            {
                $forumData = [
                    'id' => $d['forum_id'],
                    'forum_name' => $d['forum_name'],
                    'forum_icon' => $d['forum_icon'],
                    'forum_desc' => $d['forum_desc'],
                    'forum_position' => $d['forum_position'],
                    'is_locked' => $d['forum_locked'],
                    'thread_count' => $d['thread_count'],
                    'post_count' => $d['post_count'],
                ];

                $forum = new ForumStruct($forumData);
                $builded_data[$d['category_id']]->forums[$forum->id] = $forum;
            }

            if($d['subforum_id'] > 0 && $d['forum_id'] == $d['subforum_forumid'])
            {
                $subforumData = [
                    'id' => $d['subforum_id'],
                    'forum_id' => $d['forum_id'],
                    'subforum_name' => $d['subforum_name'],
                    'subforum_desc' => $d['subforum_desc'],
                    'is_locked' => $d['subforum_locked'],
                ];
                
                $subforum = new SubForumStruct($subforumData);
                $builded_data[$d['category_id']]->forums[$d['forum_id']]->subforums[$subforum->id] = $subforum;
            }
        }

        $lastPostTemplate = '';
        $categoryTemplate = '';
        $forumTemplate = '';
        $subforumTemplate = '';
        foreach ($builded_data as $category)
        {
            $categoryTemplate = new Template('index/forum', 'category');
            $forums = '';

            foreach($category->forums as $forum)
            {
                $subforums = '';
                $forumTemplate = new Template('index/forum', 'forum');

                foreach($forum->subforums as $subforum)
                {
                    $subforumTemplate = new Template('index/forum', 'subforum');
                    $subforumTemplate->AddEntry('{subforum_title}', $subforum->subforum_name);
                    $subforumTemplate->AddEntry('{subforum_id}', $subforum->id);
                    $subforumTemplate->Replace();

                    $subforums .= $subforumTemplate->template;
                }

                $lastPostTemplate = new Template('index/forum', 'forum_nolastpost');
                if ($forum->post_count > 0)
                {
                    $lastPost = ForumController::GetLastPost($this->database, $forum->id);

                    $lastPostTemplate = new Template('index/forum', 'forum_lastpost');
                    $lastPostTemplate->AddEntry('{user_id}', $lastPost['id']);
                    $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                    $lastPostTemplate->AddEntry('{thread_id}', $lastPost['thread_id']);
                    $lastPostTemplate->AddEntry('{thread_title}', $lastPost['thread_title']);
                    $lastPostTemplate->AddEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                    $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                    $lastPostTemplate->Replace();
                }

                $forumTemplate->AddEntry('{forum_id}', $forum->id);
                $forumTemplate->AddEntry('{forum_icon}', $forum->forum_icon);
                $forumTemplate->AddEntry('{forum_title}', $forum->forum_name);
                $forumTemplate->AddEntry('{forum_description}', $forum->forum_desc);
                $forumTemplate->AddEntry('{forum_posts}', $forum->post_count);
                $forumTemplate->AddEntry('{forum_threads}', $forum->thread_count);
                $forumTemplate->AddEntry('{subforums}', $subforums);
                $forumTemplate->AddEntry('{forum_lastpost}', $lastPostTemplate->template);
                $forumTemplate->Replace();

                $forums .= $forumTemplate->template;
            }

            $categoryTemplate->AddEntry('{category_icon}', $category->category_icon);
            $categoryTemplate->AddEntry('{category_title}', $category->category_name);
            $categoryTemplate->AddEntry('{category_description}', $category->category_desc);
            $categoryTemplate->AddEntry('{forums}', $forums);
            $categoryTemplate->Replace();

            $this->cats .= $categoryTemplate->template;
        }
    }

    public function Do()
    {
        $this->FetchCategories();
        $this->FetchLastAccount();
        $this->FetchStats();

        $lastRegistered = new Template('index/stats', 'last_registered');
        $lastRegistered->AddEntry('{id}', $this->lastAccount['id']);
        $lastRegistered->AddEntry('{avatar}', $this->lastAccount['avatar']);
        $lastRegistered->AddEntry('{username}', $this->lastAccount['formatted_username']);
        $lastRegistered->AddEntry('{regdate}', RelativeTime::Convert($this->lastAccount['reg_date']));
        $lastRegistered->Replace();

        $statsTemplate = new Template('index', 'stats');
        $statsTemplate->AddEntry('{totalPosts}', $this->totalPosts);
        $statsTemplate->AddEntry('{totalThreads}', $this->totalThreads);
        $statsTemplate->AddEntry('{totalUsers}', $this->totalUsers);
        $statsTemplate->AddEntry('{lastRegistered}', $lastRegistered->template);
        $statsTemplate->Replace();

        $this->template = new Template('index', 'index');
        $this->template->AddEntry('{categories}', $this->cats);
        $this->template->AddEntry('{stats}', $statsTemplate->template);
        
        parent::RenderPage('index');
    }
}

?>