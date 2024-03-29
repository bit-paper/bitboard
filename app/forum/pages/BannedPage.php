<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\Database;
use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\Permissions;
use App\Interfaces\PageInterface;

class BannedPage extends PageBase implements PageInterface
{
    public function __construct(Database $db, object $data)
    {
        parent::__construct($db, $data);
        $this->forumDesc = 'You are banned!';
        $this->UrlHandler();
    }

    private function UrlHandler()
    {
        if($_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM))
            UrlManager::Redirect(UrlManager::GetPath());
    }

    public function Do()
    {
        $this->template = new Template('banned', 'banned');
        parent::RenderPage('banned');
    }
}

?>