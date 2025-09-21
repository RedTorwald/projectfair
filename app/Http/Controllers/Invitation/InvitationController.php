<?php

namespace App\Http\Controllers\Invitation;

use App\Http\Controllers\Controller;
use App\Http\Services\InvitationService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    protected $service;

    public function __construct(InvitationService $service)
    {
        $this->service = $service;
    }

    public function send(Request $request)
    {
        $request->validate([
            'candidate_id' => 'required|integer|exists:candidates,id',
            'project_id'   => 'required|integer|exists:projects,id',
        ]);

         $supervisor = $request->get('supervisor');

        $invitation = $this->service->sendInvitation($request->all(), $supervisor);
        return response()->json($invitation, 201);
    }

    public function accept($id)
    {
        $invitation = $this->service->acceptInvitation($id);
        return response()->json([
            'message'    => 'Приглашение принято',
            'invitation' => $invitation,
        ]);
    }

    public function reject($id)
    {
        $invitation = $this->service->rejectInvitation($id);
        return response()->json([
            'message'    => 'Приглашение отклонено',
            'invitation' => $invitation,
        ]);
    }

    public function invitationsForProject(Request $request, int $projectId)
    {
        $supervisor = $request->get('supervisor');
        $invitations = $this->service->getInvitationsByProject($projectId, $supervisor);
        return response()->json($invitations);
    }

    public function invitationsForCandidate(Request $request, int $candidateId)
    {
        $candidate = $request->get('candidate');
        $invitations = $this->service->getInvitationsByCandidate($candidateId, $candidate);
        return response()->json($invitations);
    }

    public function invitationForProjectSupervisor(Request $request, int $invitationId)
    {
        $supervisor = $request->get('supervisor');
        $invitation = $this->service->getInvitationForProjectSupervisor($invitationId, $supervisor);
        return response()->json($invitation);
    }

    public function invitationForCandidate(Request $request, int $invitationId)
    {
        $candidate = $request->get('candidate');
        $invitation = $this->service->getInvitationForCandidate($invitationId, $candidate);
        return response()->json($invitation);
    }

}
