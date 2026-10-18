<?php

namespace App\Domain\Ai;

use App\Models\User;

/**
 * Which module permission an assistant tool needs on top of the general
 * "ai-assistant.report" / "ai-assistant.act" gates. Without it, anyone who
 * could open the assistant could read every module's data through chat
 * (attendance, wallets, deals...) even where the panel itself would refuse
 * them. Tools not listed here are cross-cutting (alerts) and keep only the
 * general gate; action tools carry their own resource permission already.
 */
class AiToolAccess
{
    private const TOOL_MODULES = [
        'get_accommodation_summary' => 'bookings',
        'get_occupancy_forecast' => 'bookings',
        'search_bookings' => 'bookings',
        'get_upcoming_arrivals' => 'bookings',
        'get_upcoming_departures' => 'bookings',
        'get_overdue_checkouts' => 'bookings',
        'get_revenue_by_room_type' => 'bookings',
        'get_booking_source_breakdown' => 'bookings',
        'get_cancellation_summary' => 'bookings',
        'get_channel_manager_summary' => 'sales-channels',
        'get_ticketing_sales_summary' => 'orders',

        'get_crm_summary' => 'guests',
        'get_guest_detail' => 'guests',
        'search_guests' => 'guests',
        'get_top_guests_by_value' => 'guests',
        'get_wallet_activity_summary' => 'guests',
        'get_breached_support_tickets' => 'guest-tickets',
        'get_deals_by_assignee' => 'deals',
        'get_deal_source_breakdown' => 'deals',
        'get_guest_satisfaction_summary' => 'guest-feedback',
        'get_trip_planner_summary' => 'attractions',

        'get_seo_overview' => 'seo-projects',
        'get_seo_traffic_trend' => 'seo-projects',
        'get_seo_top_queries' => 'seo-projects',
        'get_seo_top_pages' => 'seo-projects',
        'get_seo_keyword_rankings' => 'seo-projects',
        'get_seo_keyword_rank_history' => 'seo-projects',
        'get_seo_open_opportunities' => 'seo-projects',
        'get_seo_opportunity_outcomes_summary' => 'seo-projects',
        'get_seo_technical_audit_summary' => 'seo-projects',
        'get_seo_content_calendar_status' => 'seo-projects',
        'get_seo_rank_movers' => 'seo-projects',

        'get_project_health_summary' => 'projects',
        'get_project_detail' => 'projects',
        'get_overdue_project_phases' => 'projects',
        'get_project_budget_summary' => 'projects',
        'get_project_expenses' => 'projects',

        'get_attendance_summary' => 'attendance-records',
        'search_attendance_records' => 'attendance-records',
        'get_not_checked_in_today' => 'attendance-records',
        'get_attendance_by_user' => 'attendance-records',
        'get_department_attendance_summary' => 'attendance-records',
        'get_unusual_device_checkins' => 'attendance-records',
        'get_missing_checkouts' => 'attendance-records',
        'get_overtime_undertime_summary' => 'attendance-records',
        'get_attendance_trend' => 'attendance-records',

        'get_task_summary' => 'tasks',
        'search_tasks' => 'tasks',
        'get_overdue_tasks' => 'tasks',
        'get_tasks_by_assignee' => 'tasks',
        'get_task_priority_breakdown' => 'tasks',

        'get_form_submissions_summary' => 'form-submissions',
        'get_daily_checklist_compliance' => 'form-submissions',
        'get_workflow_bottleneck_analysis' => 'form-submissions',
        'get_approver_performance' => 'form-submissions',
        'get_rejection_reasons' => 'form-submissions',
        'compare_form_submissions_between_periods' => 'form-submissions',
        'search_form_submissions' => 'form-submissions',
        'summarize_submission_for_decision' => 'form-submissions',
        'get_at_risk_sla_submissions' => 'form-submissions',
        'get_breached_sla_submissions' => 'form-submissions',
        'get_workflow_health_check' => 'workflow-definitions',
        'get_form_template_fields' => 'form-templates',

        'create_task' => 'tasks',
    ];

    /**
     * The module a search_everything result type belongs to; a result whose
     * module the user cannot list is dropped from their search.
     */
    private const SEARCH_RESULT_MODULES = [
        'وظیفه' => 'tasks',
        'پروژه' => 'projects',
        'معامله (CRM)' => 'deals',
        'قرارداد' => 'contracts',
        'سند' => 'documents',
        'تیکت پشتیبانی' => 'guest-tickets',
        'شرکت' => 'companies',
        'پاسخ ثبت‌شده در فرم' => 'form-submissions',
        'ردیف جدول در یک فرم ثبت‌شده' => 'form-submissions',
    ];

    public static function allowsTool(?User $user, string $tool): bool
    {
        if (! $user) {
            return false;
        }

        $module = self::TOOL_MODULES[$tool] ?? null;

        if ($module === null) {
            return true;
        }

        return $user->can($tool === 'create_task' ? "{$module}.create" : "{$module}.view_any");
    }

    public static function allowsSearchResult(?User $user, string $resultType): bool
    {
        $module = self::SEARCH_RESULT_MODULES[$resultType] ?? null;

        return $module === null || (bool) $user?->can("{$module}.view_any");
    }
}
