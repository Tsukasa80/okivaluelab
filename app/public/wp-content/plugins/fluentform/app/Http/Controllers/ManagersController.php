<?php

namespace FluentForm\App\Http\Controllers;

use FluentForm\Framework\Validator\ValidationException;
use FluentForm\App\Services\Manager\ManagerService;

class ManagersController extends Controller
{
    public function index(ManagerService $managerService)
    {
        $result = $managerService->getManagers($this->request->all());
        return $this->sendSuccess($result);
    }
    
    public function addManager(ManagerService $managerService)
    {
        try {
            $result = $managerService->addManager($this->request->all());
            return $this->sendSuccess($result);
        } catch (ValidationException $exception) {
            return $this->sendError($exception->errors(), 422);
        }
    }
    
    public function removeManager(ManagerService $managerService)
    {
        try {
            $result = $managerService->removeManager($this->request->all());
            return $this->sendSuccess($result);
        } catch (ValidationException $exception) {
            return $this->sendError($exception->errors(), 422);
        }
    }

    public function getUsers(ManagerService $managerService)
    {
        $search = sanitize_text_field($this->request->get('search', ''));
        
        $users = get_users([
            'search' => "*{$search}*",
            'number' => 50,
            'fields'  => ['ID', 'display_name', 'user_email']
        ]);
        
        $formattedUsers = [];
        foreach ($users as $user) {
            $formattedUsers[] = [
                'ID'           => $user->ID,
                'display_name' => $user->display_name,
                'user_email'   => $user->user_email,
                'label'        => $user->display_name . ' - ' . $user->user_email,
            ];
        }

        return $this->sendSuccess([
            'users' => $formattedUsers,
        ]);
    }
}
