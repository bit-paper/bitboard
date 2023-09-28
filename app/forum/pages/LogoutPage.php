<?php

namespace App\Forum\Pages;

use App\Classes\PageBase;
use App\Classes\SessionManager;
use App\Classes\UrlManager;

use App\Interfaces\PageInterface;

class LogoutPage extends PageBase implements PageInterface
{
    public function __construct($database, array $data)
    {
        $this->Do();
    }

    public function Do()
    {
        if(!SessionManager::IsLogged())
        {
            UrlManager::Redirect($this->serverPath);
            return;
        }

        SessionManager::Delete();
        UrlManager::Redirect($this->serverPath);
    }
}

?>