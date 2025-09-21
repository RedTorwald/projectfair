<?php

namespace App\Http\Services;

use App\Models\Candidate;
use App\Models\Invitation;
use App\Models\ProjectSupervisor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\ProjectStateEnum;


class InvitationService
{
    /**
     * Отправка приглашения студенту
     */
    public function sendInvitation(array $data, $supervisor): Invitation
    {
        // Проверяем доступ наставника
    $hasAccess = DB::table('project_supervisor')
                ->join('project_supervisor_role', 'project_supervisor_role.project_supervisor_id', '=', 'project_supervisor.id')
                ->where('project_supervisor.project_id', $data['project_id'])
                ->where('project_supervisor.supervisor_id', $supervisor->id)
                ->pluck('project_supervisor_role.project_supervisor_role_id') // 2 = руководитель, 3 = со-наставник
                ->toArray();

        if (!array_intersect([2, 3], $hasAccess)) {
            abort(403, 'Вы не имеете прав для отправки приглашения');
        }

        // Проверяем состояние проекта
        $project = Project::findOrFail($data['project_id']);
        if (!in_array($project->state_id, [
            ProjectStateEnum::recruitment->value,
        ])) {
            abort(407, 'Приглашения можно отправлять только в проекты со статусом "Идёт набор"');
        }

        // Проверяем, есть ли уже активное приглашение этому студенту
        $existing = Invitation::where('candidate_id', $data['candidate_id'])
            ->where('project_id', $data['project_id'])
            ->where('status', 'accepted')
            ->first();

        if ($existing) {
            abort(409, 'Студент уже имеет активное приглашение в этот проект');
        }

        return Invitation::create([
            'status'        => 'pending',
            'candidate_id'  => $data['candidate_id'],
            'project_id'    => $data['project_id'],
            'supervisor_id' => $supervisor->id,
        ]);
    }


    /**
     * Принятие приглашения студентом
     */
    public function acceptInvitation(int $invitationId): Invitation
    {
        return DB::transaction(function () use ($invitationId) {
            $invitation = Invitation::lockForUpdate()->findOrFail($invitationId);

            if ($invitation->status !== 'pending') {
                abort(409, 'Приглашение уже неактивно');
            }

            // Проверяем, что студент не принял другое приглашение
            $alreadyAccepted = Invitation::where('candidate_id', $invitation->candidate_id)
                ->where('status', 'accepted')
                ->exists();

            if ($alreadyAccepted) {
                abort(409, 'Студент уже принял другое приглашение');
            }

            $invitation->update([
                'status'       => 'accepted',
                'responded_time' => Carbon::now(),
            ]);

            // Остальные приглашения отклоняем
            Invitation::where('candidate_id', $invitation->candidate_id)
                ->where('id', '!=', $invitation->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'declined',
                    'responded_time' => Carbon::now(),
                ]);

            return $invitation;
        });
    }

    /**
     * Отклонение приглашения студентом
     */
    public function rejectInvitation(int $invitationId): Invitation
    {
        $invitation = Invitation::findOrFail($invitationId);

        if ($invitation->status !== 'pending') {
            abort(409, 'Приглашение уже неактивно');
        }


        $invitation->update([
            'status'       => 'declined',
            'responded_time' => Carbon::now(),
        ]);

        return $invitation;
    }

    /**
     * Получить приглашения для проекта
     */
    public function getInvitationsByProject(int $projectId, $supervisor)
    {
        $hasAccess = ProjectSupervisor::where('project_id', $projectId)
            ->where('supervisor_id', $supervisor->id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'У вас нет доступа к приглашениям этого проекта');
        }

        return Invitation::where('project_id', $projectId)
            ->where('supervisor_id', $supervisor->id)
            ->get();
    }

    /**
     * Получить приглашения для студента
     */
    public function getInvitationsByCandidate(int $candidateId, $candidate)
    {
    // Проверяем доступ студента
        $hasAccess = Candidate::where('id', $candidateId)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'У вас нет доступа к чужим приглашениям');
        }

        // Если студент, возвращаем его приглашения
        return Invitation::where('candidate_id', $candidateId)->get();

    }

    /**
     * Получить конкретное приглашение для наставника
     */
    public function getInvitationForProjectSupervisor(int $invitationId, $supervisor): Invitation
    {
        $invitation = Invitation::findOrFail($invitationId);

        // Проверяем, что наставник имеет доступ к этому проекту
        $hasAccess = ProjectSupervisor::where('project_id', $invitation->project_id)
            ->where('supervisor_id', $supervisor->id)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'У вас нет доступа к этому приглашению');
        }

        return $invitation;
    }

    /**
     * Получить конкретное приглашение для студента
     */
    public function getInvitationForCandidate(int $invitationId, $candidate): Invitation
    {
        $invitation = Invitation::findOrFail($invitationId);

        // Проверяем, что студент запрашивает своё приглашение
        if ($invitation->candidate_id !== $candidate->id) {
            abort(403, 'У вас нет доступа к этому приглашению');
        }

        return $invitation;
    }
    
}
