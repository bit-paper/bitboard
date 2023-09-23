<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\UrlManager;
use App\Classes\SessionManager;
use App\Classes\AvatarUtils;

class HeaderWidget
{
    public $Template;
    private string $theme;

    public function __construct(string $theme)
    {
        $this->theme = $theme;
        $this->Template = new Template('./themes/' . $this->theme . '/templates/header/header.html');
        $this->Do();
    }

    private function Do()
    {
        $ServerPath = UrlManager::GetPath();
        $menuTemplate = new Template('./themes/' . $this->theme . '/templates/header/nav/menu.html');

        $this->Template->AddEntry('{menu}', $menuTemplate->templ);
        $this->Template->AddEntry('{server_url}', $ServerPath);

        if (SessionManager::IsLogged())
        {
            $userTemplate = new Template('./themes/' . $this->theme . '/templates/header/nav/user.html');
            $userTemplate->AddEntry('{avatar}', AvatarUtils::GetPath($this->theme, $_SESSION['bitboard_user']['avatar']));
            $userTemplate->AddEntry('{username}', $_SESSION['bitboard_user']['username']);
            $userTemplate->Replace();

            $userMenuTemplate = new Template('./themes/' . $this->theme . '/templates/header/nav/user_menu.html');
            $userMenuTemplate->AddEntry('{server_url}', $ServerPath);
            $userMenuTemplate->Replace();

            $this->Template->AddEntry('{user}', $userTemplate->templ);
            $this->Template->AddEntry('{login_register}', '');
            $this->Template->AddEntry('{user_menu}', $userMenuTemplate->templ);
        }
        else
        {
            $loginRegisterTemplate = new Template('./themes/' . $this->theme . '/templates/header/nav/login_register.html');
            $loginRegisterTemplate->AddEntry('{server_url}', $ServerPath);
            $loginRegisterTemplate->Replace();
            
            $this->Template->AddEntry('{user}', '');
            $this->Template->AddEntry('{login_register}', $loginRegisterTemplate->templ);
        }

        $this->Template->Replace();
    }
}

?>