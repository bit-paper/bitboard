<?php

namespace App\Forum\Pages;

use App\Classes\Database;
use App\Classes\PageBase;
use App\Classes\Template;
use App\Classes\Avatar;
use App\Classes\AvatarUtils;
use App\Classes\UsernameUtils;
use App\Classes\RelativeTime;
use App\Classes\UrlManager;

use App\Interfaces\PageInterface;

use App\Forum\Widgets\PaginationWidget;

use App\Forum\Controllers\ThreadController;
use App\Forum\Controllers\SubForumController;
use App\Forum\Widgets\PrefixWidget;

class ForumPage extends PageBase implements PageInterface
{
    private string $subforums = '';
    private string $threads = '';

    private int $forumPage;
    private int $forumID;

    private int $maximumResults = 5;
    private int $threadsCount = 0;

    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if (
            (count($this->forumData->actionParameters) < 4) || // Check if the URL has enough segments
            ($this->forumData->actionParameters[2] !== 'page') || // Check if the segment after 'forum' is 'page'
            !is_numeric($this->forumData->actionParameters[1]) || // Check if the second segment is a numeric forum ID
            !is_numeric($this->forumData->actionParameters[3]) // Check if the fourth segment is a numeric page number
        )
            UrlManager::Redirect($this->serverPath . 'forum/1/page/1');

        $this->forumPage = $this->forumData->actionParameters[3];
        $this->forumID = $this->forumData->actionParameters[1];

        if ($this->forumPage <= 0 || $this->forumID <= 0)
            UrlManager::Redirect($this->serverPath . 'forum/1/page/1');

        $forumID = $this->database->Query('SELECT id FROM bit_forums WHERE id = ?', $this->forumID)->FetchArray();
        if (empty($forumID))
            UrlManager::Redirect($this->serverPath . 'forum/1/page/1');
    }

    private function FetchSubForums()
    {
        $subforums = $this->database->Query('SELECT sf.id, sf.subforum_name, sf.subforum_desc, 
            COUNT(DISTINCT p.id) AS post_count,
            COUNT(DISTINCT t.id) AS thread_count
            FROM bit_subforums AS sf
            LEFT JOIN bit_threads AS t ON t.subforum_id = sf.id
            LEFT JOIN bit_posts AS p ON p.thread_id = t.id
            WHERE sf.forum_id = ?
            GROUP BY sf.id
            ORDER BY sf.id ASC',
            [$this->forumID]
        )->FetchAll();

        if (count($subforums) <= 0)
            return;

        foreach ($subforums as $subforum)
        {
            $lastPost = SubForumController::GetLastPost($this->database, $this->forumID, $subforum['id']);

            $lastPostTemplate = new Template('forum/subforum', 'subforum_nolastpost');
            if(!empty($lastPost))
            {
                $lastPostTemplate = new Template('forum/subforum', 'subforum_lastpost');
                $lastPostTemplate->AddEntry('{user_id}', $lastPost['id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                $lastPostTemplate->Replace();
            }

            $subforumTemplate = new Template('forum/subforum', 'subforum');
            $subforumTemplate->AddEntry('{id}', $subforum['id']);
            $subforumTemplate->AddEntry('{subforum_title}', $subforum['subforum_name']);
            $subforumTemplate->AddEntry('{subforum_desc}', $subforum['subforum_desc']);
            $subforumTemplate->AddEntry('{post_count}', $subforum['post_count']);
            $subforumTemplate->AddEntry('{thread_count}', $subforum['thread_count']);
            $subforumTemplate->AddEntry('{subforum_lastpost}', $lastPostTemplate->template);
            $subforumTemplate->Replace();

            $this->subforums .= $subforumTemplate->template;
        }
    }

    private function FetchThreads()
    {
        $this->threadsCount = $this->database->Query('SELECT id FROM bit_threads WHERE forum_id = ? AND subforum_id = -1', $this->forumID)->NumRows();
       
        $totalPages = ceil($this->threadsCount / $this->maximumResults);

        if ($this->forumPage > $totalPages && $this->threadsCount > 0)
        {
            UrlManager::Redirect($this->serverPath . 'forum/' . $this->forumID . '/page/' . $totalPages);
            return;
        }
       
        $threads = $this->database->Query('SELECT bit_threads.*, bit_accounts.id AS author_id, bit_accounts.username, bit_accounts.rank_id, bit_ranks.rank_format, 
            COUNT(bit_posts.id) AS post_count, MAX(bit_posts.post_timestamp) AS latest_post_timestamp
            FROM bit_threads 
            LEFT JOIN bit_posts ON bit_threads.id = bit_posts.thread_id 
            LEFT JOIN bit_accounts ON bit_threads.user_id = bit_accounts.id 
            LEFT JOIN bit_ranks ON bit_accounts.rank_id = bit_ranks.id 
            WHERE forum_id = ? AND subforum_id = -1 
            GROUP BY bit_threads.id 
            ORDER BY bit_threads.is_pinned DESC, latest_post_timestamp DESC, bit_threads.thread_timestamp DESC
            LIMIT ?, ?',
            [$this->forumID, ($this->forumPage - 1) * $this->maximumResults, $this->maximumResults]
        )->FetchAll();

        if ($this->threadsCount <= 0)
        {
            $nothreadsTemplate = new Template('forum/thread', 'thread_nothreads');
            $this->threads = $nothreadsTemplate->template;
            return;
        }

        $threadPrefix = '';
        foreach ($threads as $thread) 
        {
            $lastPostTemplate = new Template('forum/thread', 'thread_nolastpost');
            $threadPrefix = '';

            if ($thread['post_count'] > 0) {
                $lastPost = ThreadController::GetLastPost($this->database, $thread['id']);

                $lastPostTemplate = new Template('forum/thread', 'thread_lastpost');
                $lastPostTemplate->AddEntry('{user_id}', $lastPost['id']);
                $lastPostTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($lastPost['avatar']));
                $lastPostTemplate->AddEntry('{post_date}', RelativeTime::Convert($lastPost['post_timestamp']));
                $lastPostTemplate->AddEntry('{username}', UsernameUtils::Format($lastPost['rank_format'], $lastPost['username']));
                $lastPostTemplate->Replace();
            }

            $threadTemplate = new Template('forum/thread', 'thread');

            if ($thread['is_closed'])
            {
                $threadPrefix = new PrefixWidget($this->database, 1);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{closed_prefix}', $threadPrefix);
            $threadPrefix = '';

            if ($thread['is_pinned'])
            {
                $threadPrefix = new PrefixWidget($this->database, 2);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{pinned_prefix}', $threadPrefix);
            $threadPrefix = '';

            if ($thread['prefix_id'] > 2)
            {
                $threadPrefix = new PrefixWidget($this->database, $thread['prefix_id']);
                $threadPrefix = $threadPrefix->template;
            }

            $threadTemplate->AddEntry('{custom_prefix}', $threadPrefix);
            $threadPrefix = '';

            $threadTemplate->AddEntry('{id}', $thread['id']);
            $threadTemplate->AddEntry('{thread_title}', $thread['thread_title']);
            $threadTemplate->AddEntry('{thread_author_id}', $thread['author_id']);
            $threadTemplate->AddEntry('{thread_author_username}', UsernameUtils::Format($thread['rank_format'], $thread['username']));
            $threadTemplate->AddEntry('{thread_date}', RelativeTime::Convert($thread['thread_timestamp']));
            $threadTemplate->AddEntry('{thread_replies}', $thread['post_count']);
            $threadTemplate->AddEntry('{thread_lastpost}', $lastPostTemplate->template);
            $threadTemplate->Replace();

            $this->threads .= $threadTemplate->template;
        }
    }

    private function SetupForumDesc()
    {
        $forumName = $this->database->Query('SELECT forum_name FROM bit_forums WHERE id = ?', $this->forumID)->FetchArray();
        $this->forumDesc = 'Forum ' . $forumName['forum_name'];
    }

    public function Do()
    {
        $this->SetupForumDesc();
        $this->FetchSubForums();
        $this->FetchThreads();

        $paginationTemplate = new PaginationWidget($this->forumPage, $this->threadsCount, $this->maximumResults, 'forum/' . $this->forumID . '/page/');

        $this->template = new Template('forum', 'forum');
        $this->template->AddEntry('{subforums}', $this->subforums);
        $this->template->AddEntry('{threads}', $this->threads);
        $this->template->AddEntry('{pagination}', $this->threadsCount > $this->maximumResults ? $paginationTemplate->Template->template : '');
        
        parent::RenderPage('forum');
    }
}

?>