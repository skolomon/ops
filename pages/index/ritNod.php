<?php

/**
 * @file pages/index/ritNod.php
 *
 * Copyright (c) 2023 Sasz Kolomon
 *
 * @class RitNod
 * @RIT NOD functions
 *
 * @brief Class for retrieving users from RIT NOD and adding to OPS.
 */


use APP\facades\Repo;
use PKP\core\Core;
// use PKP\session\SessionManager;
use PKP\security\Validation;
use PKP\security\Role;
use APP\core\Request;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\core\PKPApplication;
use APP\core\Application;
use APP\notification\NotificationManager;
use APP\notification\Notification;
// use PKP\notification\PKPNotification;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\mailables\EditorAssigned;
use PKP\log\SubmissionEmailLogEntry;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;

use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\mail\mailables\SubmissionNeedsEditor;

class RitNod //extends Validation
{
    public static function loginFromRitNod($request)
    {
        $profileId = $_GET['profile_id'];
        $lang = $_GET['lang'] ?? null;

        $session = $request->getSession();
        $source = $session->getSessionVar('source');
        $session->setSessionVar('source', null);

        if (isset($profileId)) {
            if (Validation::isLoggedIn()) {
                Validation::logout();
            }
            $url = "https://opensi.nas.gov.ua/all/GetProfileByKey";

            $body = http_build_query(['token' => $profileId]);
            $opts = [
                'http' => [
                    'method'=>"GET",
                    'header' =>
                        "Content-Type: application/x-www-form-urlencoded",
                    'content' => $body
                ]
            ];
            $context = stream_context_create($opts);

            $userInfo = file_get_contents($url, false, $context);

            if (!$userInfo) {
                self::displayErrorModal($request, __('login.error.title'), __('login.error.description', ['error' => __('login.error.noresponce')]));
                return true;
            }
            $userProf = json_decode($userInfo);
            if (isset($userProf->Key) && $userProf->Key === "error") {
                self::displayErrorModal($request, __('login.error.title'), __('login.error.description', ['error' => $userProf->Value ?? $userInfo]));
                return true;
            }
            if (!self::checkUserProfile($request, $userProf)) {
                return true;
            }

            $reason = null;
            $password = Validation::generatePassword();
            [$username, $userId, $notVerified] = self::addOrUpdateUserProf($userProf, $password);

            if (isset($username)) {
                self::assignUserRoles($request, $userId);

                // Associate the new user with the existing session
                // $sessionManager = SessionManager::getManager();
                // $session = $sessionManager->getUserSession();
                $session->setSessionVar('username', $username);
                $session->setSessionVar('profileId', $profileId);

                if (isset($lang)) {
                    self::setUserLocale($request, $lang);
                }

                ///////
                //Договір приєднання
                if ($notVerified) {
                    //reload page once
                    if (!isset($_GET['r'])) { //needed to init server environment (don't know why so)
                        $request->redirect(null, 'index', null, null, ['profile_id' => $profileId, 'r' => '1']);
                    }
                    $userAgreedKey = self::getDogovirKey($request);
                    $templateMgr = TemplateManager::getManager($request);

                    $agreementText = file_get_contents('templates/ritNod/agreementText.html');
                    $agreementText = preg_replace('/\<style\>[\S\s]*\<\/style\>/m', '', $agreementText);
                    $agreementText = str_replace('{$path}', $request->getBaseUrl(), $agreementText);

                    $templateMgr->assign([
                        'agreementText' => $agreementText,
                        'name' => $userProf->prizvische_ua . ' ' . $userProf->imya_ua . ' ' . $userProf->pobatkovi_ua,
                        'name_en' => $userProf->imya_en . ' ' . $userProf->prizvische_en,
                        'path' => $request->getBaseUrl()
                    ]);
                    $request->setCookieVar("dogovir", $userAgreedKey, time() + 60 * 60);
                    $templateMgr->display('/ritNod/agreement.tpl');
                    return false;
                }

                ///////
                Validation::login($username, $password, $reason, true);
                // if (isset($lang)) {
                //     self::setUserLocale($request, $lang);
                // }

                if ($source) {
                    $request->redirectUrl($source);
                } else {
                    $request->redirect(null, "index");
                }
            }
        }
        return true;
    }

    public static function getDogovirKey($request)
    {
        $session = $request->getSession();
        $username = $session->getSessionVar('username');
        if (!$username) {
            return null;
        }
        $user = Repo::user()->getByUsername($username, true);
        if (!$user || !$user->getPassword()) {
            return null;
        }
        return 'k' . md5($user->getPassword() . date('yd'));
    }

    public static function verifyUser($request)
    {
        $session = $request->getSession();
        $username = $session->getSessionVar('username');
        if (!$username) {
            $request->redirect(null, 'login', 'signOut');
        }
        $user = Repo::user()->getByUsername($username, true);
        if (!$user || !$user->getPassword()) {
            $request->redirect(null, 'login', 'signOut');
        }

        $user->setDateValidated(Core::getCurrentDate()); //Ознака Договору приєднання 
        $password  = Validation::generatePassword();
        $user->setPassword(Validation::encryptCredentials($username, $password));
        $user->setMustChangePassword(0);

        Repo::user()->edit($user);

        $reason = null;
        Validation::login($username, $password, $reason, true);
        $request->redirect(null, "index");
    }

    public static function checkUserProfile($request, $userProf)
    {
        $errors = '';
        $checkPoints = ['imya_ua', 'prizvische_ua', 'imya_en', 'prizvische_en', 'agreement_publ']; //'ORCID'?
        foreach ($checkPoints as $point) {
            if (!isset($userProf->$point) || !$userProf->$point) {
                $errors .= '<li>' . __('profile.error.' . $point) . '</li>';
            }
        }

        if ($errors) {
            $message = __('profile.error.description', ['errors' => '<ul>' . $errors . '</ul>']);
            self::displayErrorModal($request, __('profile.error.title'),  $message);
            return false;
        }
        return true;
    }

    public static function assignUserRoles($request, $userId, $moderator = false)
    {
        if ($request->getContext() /*&& isset($user) */ /*&& isset($_GET['profile_id'])*/) {
            $contextId = $request->getContext()->getId();

            if($moderator) {
                $defaultGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_SUB_EDITOR], $contextId, true)->first();
                if ($defaultGroup && !Repo::userGroup()->userInGroup($userId, $defaultGroup->getId())) {
                    Repo::userGroup()->assignUserToGroup($userId, $defaultGroup->getId());
                }
            } else {
                $defaultGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_READER], $contextId, true)->first();
                if ($defaultGroup && !Repo::userGroup()->userInGroup($userId, $defaultGroup->getId())) {
                    Repo::userGroup()->assignUserToGroup($userId, $defaultGroup->getId());
                }
                $defaultGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $contextId, true)->first();
                if ($defaultGroup && !Repo::userGroup()->userInGroup($userId, $defaultGroup->getId())) {
                    Repo::userGroup()->assignUserToGroup($userId, $defaultGroup->getId());
                }
            }
        }
    }

    public static function setUserLocale($request, $lang) //"en" | "ua"
    {
        $session = $request->getSession();
        $session->setSessionVar('currentLocale', $lang == "en" ? "en" : "uk");

        // $request->redirect(null, "index");
    }

    public static function addOrUpdateUserProf($userProf, $password = null)
    {
        $email = $userProf->email;
        $username = explode("@", $email)[0];
        $firstName = $userProf->imya_ua;
        $lastName = $userProf->prizvische_ua;
        $pobatkovi = $userProf->pobatkovi_ua;
        $pobatkoviEn = $userProf->pobatkovi_en;
        $firstNameEn = $userProf->imya_en;
        $lastNameEn = $userProf->prizvische_en;
        $affiliation = $userProf->full_name_inst;
        $affiliationEn = $userProf->full_name_inst_en;
        $orcid = $userProf->ORCID;
        if ($orcid && !str_contains(strtolower($orcid), 'orcid.org')) {
            $orcid = 'https://orcid.org/' . $orcid;
        }

        if (!isset($password)) {
            $password  = Validation::generatePassword();
        }

        $user = Repo::user()->getByUsername($username, true);

        $newUser = true;
        $notVerified = true;
        if (isset($user)) {
            $newUser = false;
            if ($user->getDateValidated()) {
                $notVerified = false;
            }
        }

        // New user
        if ($newUser) {
            $user = Repo::user()->newDataObject();

            $user->setUsername($username);
            $user->setDateRegistered(Core::getCurrentDate());
            $user->setInlineHelp(1); // default new users to having inline help visible.
        }

        $user->setEmail($email);

        // The multilingual user data (givenName, familyName and affiliation) will be saved
        // in the current UI locale and copied in the site's primary locale too

        $user->setCountry("UA"); //TODO !!!

        $ual = "uk";
        $enl = "en";
        $user->setGivenName($firstName, $ual);
        $user->setFamilyName($lastName, $ual);
        $user->setAffiliation($affiliation, $ual);

        $user->setData("poBatkovi", $pobatkovi, $ual);

        if ($firstNameEn && $lastNameEn) {
            $user->setGivenName($firstNameEn, $enl);
            $user->setFamilyName($lastNameEn, $enl);
            $user->setData("poBatkovi", $pobatkoviEn, $enl);
        }
        if ($affiliationEn) {
            $user->setAffiliation($affiliationEn, $enl);
        }
        $user->setOrcid($orcid);

        $user->setPassword(Validation::encryptCredentials($username, $password));
        $user->setMustChangePassword(0);

        if ($newUser) {
            Repo::user()->add($user);
        } else {
            Repo::user()->edit($user);
        }

        $userId = $user->getId();
        if (!$userId) {
            return [null, null];
        }

        // self::assignUserRoles($request, $userId);

        return [$username, $userId, $notVerified];
    }

    public static function assignModerator(Request $request, Submission $submission):bool
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $contextId = $request->getContext()->getId();
        $defaultModerGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_SUB_EDITOR], $contextId, true)->first();
        $userGroupId = $defaultModerGroup->getId();

        // $sessionManager = SessionManager::getManager();
        // $session = $sessionManager->getUserSession();
        $session = $request->getSession();
        $profileId = $session->getSessionVar('profileId');
        $assignmentId = $session->getSessionVar('assignmentId');

        if ($assignmentId) { //avoid duplicate moderator assignment
            $stageAssignment = $stageAssignmentDao->getById($assignmentId);
            if ($stageAssignment
                && $stageAssignment->getSubmissionId() == $submission->getId()
                && $stageAssignment->getUserGroupId() == $userGroupId)
            {
                return true; //already assigned
            }
        }

        // //Look up Moderators in RIT NOD
        // $url = "https://opensi.nas.gov.ua/all/GetCuratorsPreprint";

        // $body = http_build_query(['token' => $profileId]);
        // $opts = [
        //     'http' => [
        //         // 'method'=>"GET",
        //         'content' => $body
        //     ]
        // ];
        // $context = stream_context_create($opts);

        // $moderResp = file_get_contents($url, false, $context);

        // if (!$moderResp || strpos($moderResp, "error") !== false) {
        //     return false;
        // }

        // $moderArr = json_decode($moderResp);
        // if (gettype($moderArr) !== 'array') {
        //     return false;
        // }

        $recommendOnly = false;
        $canChangeMetadata = false;

        $notificationManager = new NotificationManager();
        $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');

        // foreach ($moderArr as $moderInfo) {
        // $moderCnt = count($moderArr);

        $curators = Repo::user()
            ->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->filterByContextIds([$request->getContext()->getId()])
            ->getMany();

        if (!$curators->count()) {
            return false;
        }

        foreach ($curators as $curator) {

        // if($moderCnt>0) {
            // $moderInfo = $moderArr[rand(0, $moderCnt - 1)]; //pick random moderator
            // [, $userId] = self::addOrUpdateUserProf($moderInfo);
            $userId = $curator->getId();
            if (isset($userId)) {
                self::assignUserRoles($request, $userId, true);
                //add user to submission as moderator
                $stageAssignment = $stageAssignmentDao->build($submission->getId(), $userGroupId, $userId, $recommendOnly, $canChangeMetadata);
                $session->setSessionVar('assignmentId', $stageAssignment->getId());

                //nofify
                $user = Repo::user()->get($userId);
                // $notificationManager->createTrivialNotification($userId, PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.addedStageParticipant')]);

                // Send notification
                $notification = $notificationManager->createNotification(
                    $request,
                    $userId,
                    Notification::NOTIFICATION_TYPE_EDITOR_ASSIGN,
                    $contextId,
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submission->getId(),
                    Notification::NOTIFICATION_LEVEL_TASK
                );

                // Send email
                $emailTemplate = Repo::emailTemplate()->getByKey($contextId, EditorAssigned::getEmailTemplateKey());
                $mailable = new EditorAssigned($request->getContext(), $submission);

                // The template may not exist, see pkp/pkp-lib#9217; FIXME remove after #9202 is resolved
                if (!$emailTemplate) {
                    $emailTemplate = Repo::emailTemplate()->getByKey($contextId, 'NOTIFICATION');
                    $request = Application::get()->getRequest();
                    $mailable->addData([
                        'notificationContents' => $notificationManager->getNotificationContents($request, $notification),
                        'notificationUrl' => $notificationManager->getNotificationUrl($request, $notification),
                    ]);
                }

                $mailable
                ->from($request->getContext()->getData('contactEmail'), $request->getContext()->getData('contactName'))
                ->subject($emailTemplate->getLocalizedData('subject'))
                ->body($emailTemplate->getLocalizedData('body'))
                ->recipients([$user]);

                Mail::send($mailable);

                // Log email
                $logDao->logMailable(
                    SubmissionEmailLogEntry::SUBMISSION_EMAIL_EDITOR_ASSIGN,
                    $mailable,
                    $submission
                );

                // Log addition.
                $assignedUser = Repo::user()->get($userId, true);
                $eventLog = Repo::eventLog()->newDataObject([
                    'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                    'assocId' => $submission->getId(),
                    'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT,
                    'userId' => Validation::loggedInAs() ?? $user->getId(),
                    'message' => 'submission.event.participantAdded',
                    'isTranslated' => false,
                    'dateLogged' => Core::getCurrentDate(),
                    'userFullName' => $assignedUser->getFullName(),
                    'username' => $assignedUser->getUsername(),
                    'userGroupName' => $defaultModerGroup->getData('name')
                ]);
                Repo::eventLog()->add($eventLog);
            }
        }
        // else {
        //     return false;
        // }
        return true;
    }

    public static function informAdminNoCurator($context, $submission) {
        $managers = Repo::user()
            ->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_MANAGER])
            ->filterByContextIds([$context->getId()])
            ->getMany();

        if (!$managers->count()) {
            return;
        }

        $notificationManager = new NotificationManager();
        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        /** @var SubmissionEmailLogDAO $logDao */
        $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
        foreach ($managers as $manager) {

            // Send notification
            $notification = $notificationManager->createNotification(
                Application::get()->getRequest(),
                $manager->getId(),
                Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
                $context->getId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if subscribed to this type of emails
            $unsubscribed = in_array(
                Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $manager->getId(),
                    $context->getId()
                )
            );

            if ($unsubscribed) {
                continue;
            }

            // Send email
            $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), SubmissionNeedsEditor::getEmailTemplateKey());
            $mailable = new SubmissionNeedsEditor($context, $submission);

            // The template may not exist, see pkp/pkp-lib#9217; FIXME remove after #9202 is resolved
            if (!$emailTemplate) {
                $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), 'NOTIFICATION');
                $request = Application::get()->getRequest();
                $mailable->addData([
                    'notificationContents' => $notificationManager->getNotificationContents($request, $notification),
                    'notificationUrl' => $notificationManager->getNotificationUrl($request, $notification),
                ]);
            }

            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->subject($emailTemplate->getLocalizedData('subject'))
                ->body($emailTemplate->getLocalizedData('body'))
                ->recipients([$manager]);

            Mail::send($mailable);

            // Log email
            $logDao->logMailable(
                SubmissionEmailLogEntry::SUBMISSION_EMAIL_NEEDS_EDITOR,
                $mailable,
                $submission
            );
        }
    }

    public static function displayErrorModal($request, $title, $message)
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'title' => $title,
            'message' => $message
        ]);
        $templateMgr->display('/ritNod/errorModal.tpl');
    }    
}
