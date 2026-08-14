<?php

namespace App\Http\Controllers;

use App\Models\ContentReport;
use App\Models\PortfolioItem;
use App\Models\Review;
use App\Models\VendorProduct;
use App\Services\NotificationService;
use Illuminate\Http\Request;

// "Report this content" from the apps. Creates the pending flag that shows
// as a FLAGGED / REPORTED badge on the admin moderation page.
// Reporting twice is a silent no-op (unique per reporter + item).
class ContentReportController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function reportReview(Request $request, int $id)
    {
        Review::findOrFail($id);

        return $this->store($request, 'review', $id);
    }

    public function reportProduct(Request $request, int $id)
    {
        VendorProduct::findOrFail($id);

        return $this->store($request, 'product', $id);
    }

    public function reportPortfolio(Request $request, int $id)
    {
        PortfolioItem::findOrFail($id);

        return $this->store($request, 'portfolio_item', $id);
    }

    private function store(Request $request, string $type, int $id)
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:1000']);

        // Vendor guard authenticates a Vendor model; sanctum guard a User.
        $reporterType = $request->user() instanceof \App\Models\Vendor ? 'vendor' : 'user';

        $report = ContentReport::firstOrCreate(
            [
                'reporter_type'   => $reporterType,
                'reporter_id'     => $request->user()->id,
                'reportable_type' => $type,
                'reportable_id'   => $id,
            ],
            ['reason' => $data['reason'] ?? null]
        );

        // Only ping the moderators on a genuinely NEW report (dup re-report is
        // a no-op). Content moderation (delete/dismiss) is super_admin-only.
        if ($report->wasRecentlyCreated) {
            $this->notifications->notifyAdmins(
                ['super_admin'],
                'Content reported',
                "A {$type} was reported and needs review.",
                ['type' => 'content_report', 'reportable_type' => $type, 'reportable_id' => (string) $id]
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => $report->wasRecentlyCreated
                ? 'Report submitted — our team will review it.'
                : 'You already reported this.',
        ], $report->wasRecentlyCreated ? 201 : 200);
    }
}
