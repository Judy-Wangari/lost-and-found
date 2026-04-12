<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class CheckInactiveItems extends Command
{
    protected $signature = 'app:check-inactive-items';
    protected $description = 'Check for inactive claims and notify parties or flag to admin';

    public function handle()
    {
        // Get all approved claims
        $approvedClaims = Claim::where('status', 'approved')->get();

        foreach($approvedClaims as $claim){

            // Get the last message for this claim
            $lastMessage = Message::where('claim_id', $claim->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Use claim approval time if no messages yet
            $lastActivityTime = $lastMessage ? $lastMessage->created_at : $claim->updated_at;

            $hoursSinceLastActivity = now()->diffInHours($lastActivityTime);

            // After 72 hours --- send reminder to both parties
            if($hoursSinceLastActivity >= 72 && $hoursSinceLastActivity < 96){

                // Notify owner
                Notification::create([
                    'user_id' => $claim->claimed_by,
                    'message_body' => 'Reminder: You have a pending collection for ' . $claim->item->category . '. Please arrange collection with the finder.',
                    'type' => 'inactivity_reminder',
                    'reference_id' => $claim->id,
                    'reference_type' => 'claim',
                    'is_read' => false
                ]);

                // Notify finder
                Notification::create([
                    'user_id' => $claim->item->posted_by,
                    'message_body' => 'Reminder: You have a pending collection for your found ' . $claim->item->category . '. Please arrange collection with the owner.',
                    'type' => 'inactivity_reminder',
                    'reference_id' => $claim->id,
                    'reference_type' => 'claim',
                    'is_read' => false
                ]);

                $this->info('Sent 72hr reminder for claim ID: ' . $claim->id);
            }

            // After 96 hours (72 + 24) --- flag to admin as unresponsive
            if($hoursSinceLastActivity >= 96){

                // Update item status to unresponsive
                $claim->item->update(['status' => 'unresponsive']);

                // Notify admin
                $admin = User::where('role', 'admin')->first();
                if($admin){
                    Notification::create([
                        'user_id' => $admin->id,
                        'message_body' => 'Item flagged as unresponsive: ' . $claim->item->category . '. No activity for over 96 hours. Please follow up.',
                        'type' => 'inactivity_reminder',
                        'reference_id' => $claim->id,
                        'reference_type' => 'claim',
                        'is_read' => false
                    ]);
                }

                $this->info('Flagged claim ID: ' . $claim->id . ' as unresponsive.');
            }
        }

        $this->info('Inactivity check completed.');
    }
}