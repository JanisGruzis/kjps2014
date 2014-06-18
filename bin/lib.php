<?php

session_start();
error_reporting(-1);

require_once('config.php');

$con = mysqli_connect($db_host, $db_user, $db_password, $db_dbname);
if (mysqli_connect_errno())
{
	throw new Exception("Nevarēja pieslēgties MySQL datubāzes serverim: " . mysqli_connect_error());
}

function escape($string)
{
	$string = mysql_real_escape_string($string);
	$string = htmlentities($string, ENT_QUOTES, 'UTF-8');
	return $string;
}

function query($query)
{
	global $con;

	$res = mysqli_query($con, $query);

	if (mysqli_errno($con))
		throw new Exception('MySQL error ' . mysqli_errno($con) . ' ' . mysqli_error($con));

	return $res;
}

function get_all($res)
{
	$ans = array();
	while ($row = mysqli_fetch_array($res))
	{
		$ans[] = $row;
	}
	return $ans;
}

function register($username, $password)
{
	$_username = escape($username);
	$_password = escape($password);
	$query = sprintf('
		select id 
		from user 
		where username = "%s"
	', $_username);
	$res = query($query);

	if (!($row = mysqli_fetch_array($res))) {
		$query = sprintf('
			insert into user 
			(username, password)
			values ("%s", "%s")
		', $_username, md5($_password));
		query($query);
		return true;
	} else {
		return false;
	}
}

function login($username, $password)
{
	if (isLoggedIn()) return false;

	$_username = escape($username);
	$_password = escape($password);

	$query = sprintf('
		select id
		from user
		where password = "%s"
	', md5($_password));
	$res = query($query);

	if ($row = mysqli_fetch_array($res)) {
		$id = $row['id'];
		$_SESSION['user'] = array(
			'username' => $username,
			'id' => $id
		);
		return true;
	} else {
		return false;
	}
}

function isLoggedIn()
{
	if (!isset($_SESSION['user'])) return false;
	if (!is_array($_SESSION['user']) or !isset($_SESSION['user']['id']) or !isset($_SESSION['user']['username']))
		return false;
	$id = escape($_SESSION['user']['id']);
	$query = sprintf('
		select id
		from user
		where id = %d
	', $id);
	$res = query($query);
	return (bool)($row = mysqli_fetch_array($res));
}

function logout()
{
	if (isLoggedIn())
	{
		unset($_SESSION['user']);
		return true;
	}	
	return false;
}

function getUsers()
{
	if (!isLoggedIn()) return false;

	$query = '
		select * 
		from user
		order by username
	';
	$res = query($query);
	return get_all($res);
}

function getMessages($ids = array())
{
	if (!isLoggedIn()) return false;

	$id = escape($_SESSION['user']['id']);
	foreach ($ids as $key => $val)
		$ids[$key] = escape($val);
	$ids = implode(', ', $ids);
	$query = '
		select m.*, a.author, r.receaver
		from message m
		inner join (
			select id, username as author
			from user
		) a on m.author_id = a.id
		left join (
			select id, username as receaver
			from user
		) r on m.receaver_id = r.id
		order by time desc
	';
	if ($ids)
		$query .= sprintf('
			where (receaver_id in (%s) and author_id = %d)
				or (author_id in (%s) and receaver_id = %d)
		', $ids, $id, $ids, $id);
	$res = query($query);
	return get_all($res);
}

function addMessage($message, $userId = null)
{
	if (!isLoggedIn()) return false;
	
	$id = escape($_SESSION['user']['id']);
	$message = escape($message);
	if ($userId) $userId = escape($userId);
	$query = sprintf('
		insert into message
		(time, message, author_id, receaver_id)
		values ("%s", "%s", %d, %s)
	', date('Y-m-d H:i:s'), $message, $id, ($userId === null ? 'NULL' : $userId + '.'));
	query($query);
	return true;	
}

