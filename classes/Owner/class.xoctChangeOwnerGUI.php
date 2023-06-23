<?php

use srag\Plugins\Opencast\Model\ACL\ACLUtils;
use srag\Plugins\Opencast\Model\Event\Event;
use srag\Plugins\Opencast\Model\Event\EventRepository;
use srag\Plugins\Opencast\Model\Event\Request\UpdateEventRequest;
use srag\Plugins\Opencast\Model\Event\Request\UpdateEventRequestPayload;
use srag\Plugins\Opencast\Model\Object\ObjectSettings;
use srag\Plugins\Opencast\Model\User\xoctUser;

/**
 * Class xoctChangeOwnerGUI
 *
 * @author            Theodor Truffer <tt@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy xoctChangeOwnerGUI: ilObjOpenCastGUI
 */
class xoctChangeOwnerGUI extends xoctGUI
{
    /**
     * @var Event
     */
    protected $event;
    /**
     * @var ObjectSettings
     */
    protected $objectSettings;
    /**
     * @var ACLUtils
     */
    private $ACLUtils;
    /**
     * @var EventRepository
     */
    private $event_repository;
    /**
     * @var \ilObjUser
     */
    private $user;
    /**
     * @var \ilGlobalTemplateInterface
     */
    private $main_tpl;

    public function __construct(ObjectSettings $objectSettings, EventRepository $event_repository, ACLUtils $ACLUtils)
    {
        global $DIC;
        parent::__construct();
        $tabs = $DIC->tabs();
        $ctrl = $DIC->ctrl();
        $main_tpl = $DIC->ui()->mainTemplate();
        $this->user = $DIC->user();
        $this->main_tpl = $DIC->ui()->mainTemplate();
        $this->objectSettings = $objectSettings;
        $this->event = $event_repository->find($_GET[xoctEventGUI::IDENTIFIER]);
        $this->ACLUtils = $ACLUtils;
        $this->event_repository = $event_repository;
        $tabs->clearTargets();
        $tabs->setBackTarget(
            $this->plugin->txt('tab_back'),
            $ctrl->getLinkTargetByClass(xoctEventGUI::class)
        );
        xoctWaiterGUI::loadLib();
        $main_tpl->addCss($this->plugin->getStyleSheetLocation('default/change_owner.css'));
        $main_tpl->addJavaScript($this->plugin->getStyleSheetLocation('default/change_owner.js'));
        $ctrl->saveParameter($this, xoctEventGUI::IDENTIFIER);
    }

    protected function index()
    {
        $xoctUser = xoctUser::getInstance($this->user);
        if (!ilObjOpenCastAccess::checkAction(
            ilObjOpenCastAccess::ACTION_SHARE_EVENT,
            $this->event,
            $xoctUser,
            $this->objectSettings
        )) {
            ilUtil::sendFailure('Access denied', true);
            $this->ctrl->redirectByClass(xoctEventGUI::class);
        }
        $temp = $this->plugin->getTemplate('default/tpl.change_owner.html', false, false);
        $temp->setVariable('PREVIEW', $this->event->publications()->getThumbnailUrl());
        $temp->setVariable('VIDEO_TITLE', $this->event->getTitle());
        $temp->setVariable('L_FILTER', $this->plugin->txt('groups_participants_filter'));
        $temp->setVariable(
            'PH_FILTER',
            $this->plugin->txt('groups_participants_filter_placeholder')
        );
        $temp->setVariable('HEADER_OWNER', $this->plugin->txt('current_owner_header'));
        $temp->setVariable(
            'HEADER_PARTICIPANTS_AVAILABLE',
            $this->plugin->txt('groups_available_participants_header')
        );
        $temp->setVariable('BASE_URL', ($this->ctrl->getLinkTarget($this, '', '', true)));
        $temp->setVariable(
            'LANGUAGE',
            json_encode([
                'none_available' => $this->plugin->txt('invitations_none_available'),
                'only_one_owner' => $this->plugin->txt('owner_only_one_owner')
            ])
        );
        $this->main_tpl->setContent($temp->get());
    }

    /**
     * @param $data
     */
    protected function outJson($data)
    {
        header('Content-type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     *
     */
    protected function add()
    {
    }

    /**
     *
     */
    public function getAll()
    {
        $owner = $this->ACLUtils->getOwnerOfEvent($this->event);
        $owner_data = $owner ? ['id' => $owner->getIliasUserId(), 'name' => $owner->getNamePresentation()] : [];

        $available_user_ids = $this->getCourseMembers();
        $available_users = [];
        foreach ($available_user_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($owner && $user_id === $owner->getIliasUserId()) {
                continue;
            }
            $user = new stdClass();
            $xoctUser = xoctUser::getInstance($user_id);
            $user->id = $user_id;
            $user->name = $xoctUser->getNamePresentation();
            $available_users[] = $user;
        }

        usort($available_users, ['xoctGUI', 'compareStdClassByName']);

        $arr = [
            'owner' => $owner_data,
            'available' => $available_users,
        ];

        $this->outJson($arr);
    }

    protected function getCourseMembers(): array
    {
        $parent = ilObjOpenCast::_getParentCourseOrGroup($_GET['ref_id']);
        $p = $parent->getMembersObject();

        return array_merge($p->getMembers(), $p->getTutors(), $p->getAdmins());
    }

    /**
     * async function
     *
     * @throws xoctException
     */
    protected function setOwner()
    {
        $user_id = $_GET['user_id'];
        $this->event->setAcl(
            $this->ACLUtils->changeOwner(
                $this->event->getAcl(),
                xoctUser::getInstance($user_id)
            )
        );
        $this->event_repository->updateACL(
            new UpdateEventRequest(
                $this->event->getIdentifier(),
                new UpdateEventRequestPayload(null, $this->event->getAcl())
            )
        );
    }

    /**
     * async function
     */
    protected function removeOwner()
    {
        $this->event->setAcl($this->ACLUtils->removeOwnerFromACL($this->event->getAcl()));
        $this->event_repository->updateACL(
            new UpdateEventRequest(
                $this->event->getIdentifier(),
                new UpdateEventRequestPayload(null, $this->event->getAcl())
            )
        );
    }

    /**
     *
     */
    protected function create()
    {
    }

    /**
     *
     */
    protected function edit()
    {
    }

    /**
     *
     */
    protected function update()
    {
    }

    /**
     *
     */
    protected function confirmDelete()
    {
    }

    /**
     *
     */
    protected function delete()
    {
    }
}
