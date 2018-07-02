﻿<?php

final class MediawikiDiscord
{
	static function getUserText ($user) 
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($user, $user->getUserPage()->getFullUrl());
	}
	
	static function getPageText ($wikiPage) 
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($wikiPage->getTitle()->getFullText(), $wikiPage->getSourceURL());
	}
	
	static function getTitleText ($title)
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($title, $title->getFullURL());
	}
	
	static function getFileText ($file)
	{
		return MediawikiDiscordUtils::CreateMarkdownLink ($file->getName(), $file->getTitle()->getFullUrl());
	}
}

final class MediawikiDiscordHooks 
{	
	static function onPageContentSaveComplete ($wikiPage, $user, $content, $summary, $isMinor, $isWatch, $section, $flags, $revision, $status, $baseRevId)
	{		
		if ($status->value['new'] == true) //page is just created, there is no need to trigger second notification
		{
			return;
		}
			
		$message = "User " . MediawikiDiscord::getUserText($user) . " saved changes on page " . MediawikiDiscord::getPageText($wikiPage) . "";		
		
		DiscordNotifications::Send($message);
	}
	
	static function onPageContentInsertComplete ($wikiPage, $user) 
	{
		if ($wikiPage->getTitle()->getNamespace() == NS_FILE) //the page is file, there is no need to trigger second notification of file's page creation
		{
			return;
		}

		$message = "User " . MediawikiDiscord::getUserText($user) . " created new page " . MediawikiDiscord::getPageText($wikiPage) . "";		
		
		DiscordNotifications::Send($message);
	}
	
	static function onTitleMoveComplete ($title, $newTitle, $user, $oldid, $newid, $reason, $revision) 
	{
		$message = "User " . MediawikiDiscord::getUserText($user) . " moved page " . MediawikiDiscord::getTitleText($title) . " to " . MediawikiDiscord::getTitleText($newTitle) . "";		
		
		if (empty($reason) == false) 
		{
			$message .= " (reason: `" .  $reason . "`)";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onArticleDeleteComplete($wikiPage, $user, $reason)
	{
		if ($wikiPage->getTitle()->getNamespace() == NS_FILE) //the page is file, there is no need to trigger second notification of file's page deletion
		{
			return;
		}
		
		$message = "User " . MediawikiDiscord::getUserText($user) . " deleted page " . MediawikiDiscord::getPageText($wikiPage) . "";		
		
		if (empty($reason) == false) 
		{
			$message .= " (reason: `" .  $reason . "`)";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onArticleUndelete($title, $create, $comment)
	{
		$message = "Deleted page " . MediawikiDiscord::getTitleText($title) . " restored";		
		
		if (empty($comment) == false) 
		{
			$message .= " (comment: `" .  $comment . "`)";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onArticleProtectComplete ($wikiPage, $user, $protect, $reason, $moveonly) 
	{
		$message = "User " . MediawikiDiscord::getUserText($user) . " changed protection of page " . MediawikiDiscord::getPageText($wikiPage) . "";				
			
		if (empty($reason) == false) 
		{
			$message .= " (reason: `" .  $reason . "`)";
		}
		
		DiscordNotifications::Send($message);
	}	
	
	static function onUploadComplete($image) 
	{ 
	    global $wgUser;
		
		$isNewRevision = count($image->getLocalFile()->getHistory()) > 0;
		
		$message = "User " . MediawikiDiscord::getUserText($wgUser) . " uploaded" . ($isNewRevision ? " new version of " : " " ) . "file " . MediawikiDiscord::getFileText ($image->getLocalFile());
		
		DiscordNotifications::Send($message);
	}
	
	static function onFileDeleteComplete($file, $oldimage, $article, $user, $reason)
	{
		$message = "User " . MediawikiDiscord::getUserText($user) . " deleted file " . MediawikiDiscord::getFileText ($file);				
			
		if (empty($reason) == false) 
		{
			$message .= " (reason: `" .  $reason . "`)";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onLocalUserCreated($user, $autocreated) 
	{ 
		$message = "User " . MediawikiDiscord::getUserText($user)  . " registered";
		
		DiscordNotifications::Send($message);
	}
	
	static function onBlockIpComplete($block, $user)
	{
		$message = "User " . MediawikiDiscord::getUserText($user)  . " blocked user " . MediawikiDiscord::getUserText($block->getTarget());				
			
		if (empty($block->mReason) == false) 
		{
			$message .= " with reason: `" .  $block->mReason . "`";
		}
			
		if (($expires = strtotime($block->mExpiry))) 
		{
			$message .= " (expires: `" . date('Y-m-d H:i:s', $expires) ."`)";
		} 
		else 
		{
			$message .= " (expires: `" . $block->mExpiry  ."`)";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onUnblockUserComplete($block, $user)
	{
		$message = "User " . MediawikiDiscord::getUserText($user) . " unblocked user `" . MediawikiDiscord::getUserText($block->getTarget()) . "`";				
			
		if (empty($block->mReason) == false) 
		{
			$message .= " with reason: `" .  $block->mReason . "`";
		}
		
		DiscordNotifications::Send($message);
	}
	
	static function onUserRights($user, array $addedGroups, array $removedGroups)
	{
		$message = "Group memberships of user " . MediawikiDiscord::getUserText($user) . " have been changed.";

		if (count($addedGroups) > 0) 
		{
			$message .= " Added: `" . join(', ', $addedGroups) . "`";
		}		
		
		if (count($removedGroups) > 0) 
		{
			$message .= " Removed: `" . join(', ', $removedGroups) . "`";
		}	
	
		DiscordNotifications::Send($message);
	}
}

final class DiscordNotifications
{
	public static function Send ($message)
	{
		global $wgDiscordWebhookUrl;
		global $wgSitename;

		$content = '{ "content": "' . $message . '", "username": "' . $wgSitename . '" }';
				
		$data = array
		(
			'http' => array
			(				
				'method'  => 'POST',
				'content' => $content
			)
		);

		file_get_contents($wgDiscordWebhookUrl, false, stream_context_create($data));	
	}
}

?>
