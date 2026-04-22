# Migration: Add 'in_review' Status - Progress Tracker

## Completed Steps
- [x] Create `includes/Migrations/AddInReviewStatus.php` (new migration class with docblock, idempotency via
INFORMATION_SCHEMA, safe ALTER listing all ENUM values)
- [x] Edit `includes/Installer.php` (add migration call after AddExpiredAt)
- [x] Edit `space-booking.php` (bump SB_VERSION to 1.1.0 and plugin header)

## Pending Steps
- [ ] Test migration: Run `php includes/run_migration.php` and check `SHOW COLUMNS FROM wp_sb_bookings LIKE 'status';`
(confirm ENUM includes 'in_review')
- [ ] Test plugin activation: Deactivate/activate plugin to trigger Installer
- [ ] Verify no errors in debug.log
- [ ] Update any UI/templates/controllers using new status (if needed)

**Next**: Confirm tool results, then test commands.