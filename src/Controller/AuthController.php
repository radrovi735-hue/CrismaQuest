<?php

namespace App\Controller;

use App\Core\View;
use App\Service\AuthService;
use App\Service\Flash;
use App\Service\Session;

// Controller dedicato ai flussi auth: login, logout, registrazione, validazioni.
class AuthController
{
    public function showLoginDoc(): void
    {
        View::render('auth/loginDoc', [
            'title' => 'login.docente',
            'pageStyles' => [
                '/css/login.css',
                '/css/crismaquest-theme.css',
            ],
            'useMathJax' => false,
        ], 'loginDocLayout');
    }

    public function showRegistrazioneDoc(): void
    {
        View::render('auth/registerDoc', [
            'title' => 'register',
            'pageStyles' => [
                '/css/register.css',
                '/css/crismaquest-theme.css',
            ],
            'useMathJax' => false,
            'pageScripts' => ['/js/register.js'],
        ], 'loginDocLayout');
    }

    public function showLoginStud(): void
    {
        View::render('auth/loginStud', [
            'title' => 'login.studente',
            'pageStyles' => [
                '/css/login_studente.css',
                '/css/crismaquest-theme.css',
            ],
            'useMathJax' => false,
        ], 'loginStudLayout');
    }

    public function loginDoc(): void
    {
        $username = $_POST['inputUser'] ?? '';
        $password = $_POST['inputPassword'] ?? '';
        $auth = new AuthService();

        if ($auth->attemptDoc($username, $password)) {
            if (Session::get('user')['role'] === 'amministratore' || Session::get('user')['role'] === 'docente') {
                header('Location: /docenti/classi');
            }
            exit;
        }

        Flash::add('danger', 'auth.invalid_credentials');
        header('Location: /loginDoc');
        exit;
    }

    public function loginStud(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['pass'] ?? '';
        $auth = new AuthService();

        if ($auth->attemptStud($username, $password)) {
            if (Session::get('user')['role'] === 'studente') {
                header('Location: /studenti/dashboard');
            }
            exit;
        }

        Flash::add('danger', 'auth.invalid_credentials');
        header('Location: /loginStud');
        exit;
    }

    public function registerTeacher(): void
    {
        $name = trim($_POST['validationCustom01'] ?? '');
        $surname = trim($_POST['validationCustom02'] ?? '');
        $username = trim($_POST['validationCustomUsername'] ?? '');
        $email = trim($_POST['validationCustom03'] ?? '');
        $password = $_POST['validationCustom04'] ?? '';
        $passwordConfirm = $_POST['validationCustom05'] ?? '';
        $gdprAccepted = isset($_POST['invalidCheck']);

        $errors = [];
        if ($name === '') $errors[] = 'register.error_name_required';
        if ($surname === '') $errors[] = 'register.error_surname_required';
        if ($username === '') $errors[] = 'register.error_username_required';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'register.error_email_invalid';
        if ($password === '' || $password !== $passwordConfirm) $errors[] = 'register.error_password_mismatch';
        if (!$gdprAccepted) $errors[] = 'register.error_privacy';

        $authService = new AuthService();
        if ($authService->usernameExists($username)) $errors[] = 'register.error_username_taken';
        if ($authService->validateEmail($email) !== 1) $errors[] = 'register.error_email_taken';

        if (!empty($errors)) {
            foreach ($errors as $error) Flash::add('danger', $error);
            $_SESSION['old'] = [
                'validationCustom01' => $name,
                'validationCustom02' => $surname,
                'validationCustomUsername' => $username,
                'validationCustom03' => $email,
            ];
            header('Location: /registrazioneDoc');
            exit;
        }

        $authService->registerTeacher($name, $surname, $username, $email, $password, $passwordConfirm);
        Flash::add('success', 'register.success_teacher');
        header('Location: /loginDoc');
        exit;
    }

    public function logoutDoc(): void
    {
        (new AuthService())->logout();
        header('Location: /loginDoc');
        exit;
    }

    public function logoutStud(): void
    {
        (new AuthService())->logout();
        header('Location: /loginStud');
        exit;
    }

    public function validateEmail(): void
    {
        $email = $_GET['mail'] ?? '';
        $result = (new AuthService())->validateEmail($email);
        header('Content-Type: text/plain; charset=utf-8');
        echo $result;
        exit;
    }

    public function validateUser(): void
    {
        $idUser = isset($_GET['id_user']) ? (int) $_GET['id_user'] : 0;
        $codice = trim($_GET['codice'] ?? '');
        $errore = true;

        if ($idUser > 0 && $codice !== '') {
            $errore = !(new AuthService())->validateUser($idUser, $codice);
        }

        View::render('auth/validateUser', [
            'title' => 'validate.user.title',
            'pageStyles' => ['/css/crismaquest-theme.css'],
            'useMathJax' => false,
            'errore' => $errore,
        ], 'loginDocLayout');
    }

    public function validateUsername(): void
    {
        $username = trim($_GET['user'] ?? '');
        header('Content-Type: text/plain; charset=utf-8');
        echo (new AuthService())->usernameExists($username) ? '0' : '1';
        exit;
    }
}
