<?php

namespace App;

session_start();

use App\Classes\Database;
use App\Classes\PageFactory;
use App\Classes\File;
Use App\Classes\UrlManager;
use App\Classes\Console;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Forum\Controllers\AccountController;
use App\Forum\Pages\IndexPage;

use App\Forum\Structs\ForumDataStruct;

use Exception;

class BitBoard
{
	private File $lock;
	private ForumDataStruct $data;
	private Database $database;

	public function __construct()
	{
		$this->lock = new File('./app/install/lock');
	}

	public function Run(): void
	{
		if (!$this->IsInstalled())
		{
			new \App\Install\Setup();
			return;
		}

		require_once './app/config.php';

		$this->database = new Database($config['host'], $config['user'], $config['pass'], $config['name']);
		$this->data = new ForumDataStruct($this->database->Query('SELECT * FROM bit_settings')->FetchArray());

		define('BB_THEME', $this->data->forum_theme);

		$this->Do();
		$this->database->Close();
	}

	private function Do()
	{
		$actionParameters = isset($_GET['action']) ? $_GET['action'] : '';
		$SplitedURL = !empty($actionParameters) ? explode('/', $_GET['action']) : array();

		if(!$this->IsOnline())
		{
			if(count($SplitedURL) > 1 || empty($SplitedURL))
			{
				// Check if the user is not logged in or doesn't have permission to view a locked forum
				if((!SessionManager::IsLogged() || SessionManager::IsLogged() && !$_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM_LOCKED)) && $SplitedURL[0] !== 'login')
				{
					UrlManager::Redirect(UrlManager::GetPath() . 'offline');
					return;
				}
			}
		}

		if(SessionManager::IsLogged())
		{
			AccountController::UpdateLastActive($this->database, $_SESSION['bitboard_user']->id);
			$_SESSION['bitboard_user']->Update($this->database);
			
			// Checking is user banned. (Reality this is checking if user has permission to view the forum)
			if(!$_SESSION['bitboard_user']->HasPermission(Permissions::VIEWING_FORUM))
			{
				if(count($SplitedURL) > 1 || empty($SplitedURL))
				{
					UrlManager::Redirect(UrlManager::GetPath() . 'banned');
					return;
				}
			}
		}

		if($this->data->forum_force_login && !SessionManager::IsLogged())
		{
			if(empty($SplitedURL) || $SplitedURL[0] !== 'login')
			{
				UrlManager::Redirect(UrlManager::GetPath() . 'login');
				return;
			}
		}

		if (!empty($SplitedURL))
		{
			$this->data->actionParameters = $SplitedURL;

			try {
				$instance = PageFactory::CreatePage($this->data->actionParameters[0], $this->database, $this->data);
			} catch (Exception $e) {
				Console::Error($e->getMessage());
			}

			return; 
		}

		new IndexPage($this->database, $this->data);
	}

	private function IsInstalled(): bool
	{
		return $this->lock->Exists();
	}

	private function IsOnline(): bool
	{
		return $this->data->forum_online;
	}
}

?>