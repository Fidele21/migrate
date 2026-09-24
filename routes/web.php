<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\FineExportController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionTypeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LetterTemplateController;
use App\Http\Controllers\LetterWorkflowController;
use App\Http\Controllers\MyBoxController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportDocumentController;
use App\Http\Controllers\ReportSignatureController;
use App\Http\Controllers\RoadController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecretaryController;
use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| There is no public registration. Accounts are created by an Administrator,
| who assigns the role and district and hands the initial credentials to the
| user, who must then set their own password before reaching anything else.
|
| Route order matters where paths share a prefix: the more specific pattern
| must be declared first, or /inspection/{code} will swallow
| /inspection/{code}/new.
|
*/

/* ---------------------------------------------------------------
   Guest
   --------------------------------------------------------------- */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
     ->middleware('auth')->name('logout');

/* ---------------------------------------------------------------
   Signed in, but before the first password change
   --------------------------------------------------------------- */
Route::middleware('auth')->group(function () {
    Route::get('/password',  [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/password', [PasswordController::class, 'update'])->name('password.update');
});

/* ---------------------------------------------------------------
   The platform
   --------------------------------------------------------------- */
Route::middleware(['auth', 'password.changed'])->group(function () {

    /* ---- Overview ---- */
    Route::get('/my-box', [MyBoxController::class, 'index'])->name('mybox');
    Route::get('/',       [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/access', [ShowcaseController::class, 'index'])->name('showcase');
    Route::get('/search', [SearchController::class, 'index'])->name('entity.search');
    Route::get('/drafts', [InspectionController::class, 'drafts'])->name('inspection.drafts');

    /* ---- Register ---- */
    Route::get('/register/{code}', [RegisterController::class, 'index'])->name('register.index');

    /* ---- Recording an inspection ----
       Declared before /inspection/{code} so the specific paths win. */
    Route::get('/inspection/{code}/new',               [InspectionController::class, 'create'])->name('inspection.create');
    Route::get('/inspection/{code}/add',               [EntityController::class, 'addFile'])->name('entity.add');
    Route::get('/inspection/{code}/followup/{entity}', [InspectionController::class, 'followUp'])->name('inspection.followup');
    Route::post('/inspection/{code}',                  [InspectionController::class, 'store'])->name('inspection.store');

    /* ---- Category dashboard ---- */
    Route::get('/inspection/{code}', [InspectionTypeController::class, 'show'])->name('type.show');

    /* ---- A single inspection ---- */
    Route::get('/inspections/{inspection}',      [InspectionController::class, 'show'])->name('inspection.show');
    Route::get('/inspections/{inspection}/edit', [InspectionController::class, 'edit'])->name('inspection.edit');
    Route::get('/inspections/{inspection}/word', [InspectionController::class, 'word'])->name('inspection.word');
    Route::put('/inspections/{inspection}',      [InspectionController::class, 'update'])->name('inspection.update');
    // Ownership is the gate, not a permission: the controller admits only
    // an inspector who was on the visit.
    Route::delete('/inspections/{inspection}',   [InspectionController::class, 'destroy'])->name('inspection.destroy');

    /* ---- Premises ---- */
    Route::get('/entity/{entity}',                     [EntityController::class, 'show'])->name('entity.show');
    Route::get('/entity/{entity}/report/{inspection}', [EntityController::class, 'report'])->name('entity.report');

    /* ---- Enforcement correspondence ---- */
    Route::get('/inspections/{inspection}/letter',  [LetterController::class, 'create'])->name('letter.create');
    Route::post('/inspections/{inspection}/letter', [LetterController::class, 'store'])->name('letter.store');
    Route::get('/letters/{letter}',                 [LetterController::class, 'show'])->name('letter.show');
    Route::get('/letters/{letter}/edit',            [LetterController::class, 'edit'])->name('letter.edit');
    Route::put('/letters/{letter}',                 [LetterController::class, 'update'])->name('letter.update');
    Route::get('/letters/{letter}/word',            [LetterController::class, 'word'])->name('letter.word');
    Route::get('/letters/{letter}/docx',            [LetterTemplateController::class, 'download'])->name('letter.docx');

    Route::get('/letters/{letter}/review',   [LetterWorkflowController::class, 'review'])->name('letter.review');
    Route::post('/letters/{letter}/submit',  [LetterWorkflowController::class, 'submit'])->name('letter.submit');
    Route::post('/letters/{letter}/advance', [LetterWorkflowController::class, 'advance'])->name('letter.advance');
    Route::post('/letters/{letter}/return',  [LetterWorkflowController::class, 'returnForRevision'])->name('letter.return');
    Route::post('/letters/{letter}/issue',   [LetterWorkflowController::class, 'issue'])->name('letter.issue');

    /* ---- Documents ---- */
    Route::get('/documents/{document}/edit',            [EditorController::class, 'edit'])->name('editor.edit');
    Route::put('/documents/{document}',                 [EditorController::class, 'update'])->name('editor.update');
    Route::post('/documents/{document}/regenerate',     [EditorController::class, 'regenerate'])->name('editor.regenerate');
    Route::post('/documents/{document}/image',          [EditorController::class, 'uploadImage'])->name('editor.image');
    Route::post('/documents/{document}/attach',         [ReportDocumentController::class, 'attach'])->name('document.attach');
    Route::delete('/documents/{document}/attach/{asset}',[ReportDocumentController::class, 'detach'])->name('document.detach');

    /* ---- Reports ---- */
    Route::post('/reports/{report}/open', [ReportSignatureController::class, 'open'])->name('report.submit');
    Route::post('/reports/{report}/sign', [ReportSignatureController::class, 'sign'])->name('report.sign');
    Route::delete('/reports/{report}',    [ReportSignatureController::class, 'destroy'])->name('report.destroy');
    Route::get('/inspections/{inspection}/report/edit', [ReportDocumentController::class, 'open'])->name('report.edit');

    /* ---- Registry ---- */
    Route::get('/registry',                     [SecretaryController::class, 'desk'])->name('secretary.desk');
    Route::post('/registry/{letter}/reference', [SecretaryController::class, 'assignReference'])->name('secretary.reference');
    Route::post('/registry/{letter}/printed',   [SecretaryController::class, 'markPrinted'])->name('secretary.printed');
    Route::post('/registry/{letter}/scan',      [SecretaryController::class, 'uploadScan'])->name('secretary.scan');
    Route::post('/registry/{letter}/dispatch',  [SecretaryController::class, 'dispatch'])->name('secretary.dispatch');

    /* ---- Assignments ----
       'assignments/new' before 'assignments/{assignment}', or "new" is
       read as an assignment id. */
    Route::get('/assignments/new',                    [AssignmentController::class, 'create'])->name('assignment.create');
    Route::get('/assignments',                        [AssignmentController::class, 'index'])->name('assignment.index');
    Route::post('/assignments',                       [AssignmentController::class, 'store'])->name('assignment.store');
    Route::get('/assignments/{assignment}',           [AssignmentController::class, 'show'])->name('assignment.show');
    Route::post('/assignments/{assignment}/cancel',   [AssignmentController::class, 'cancel'])->name('assignment.cancel');

    /* ---- Fines ----
       'fines/export' before 'fines/{fine}', same reason. */
    Route::get('/fines/export',          FineExportController::class)->name('fine.export');
    Route::get('/fines',                 [FineController::class, 'index'])->name('fine.index');
    Route::get('/fines/{fine}',          [FineController::class, 'show'])->name('fine.show');
    Route::post('/fines/{fine}/confirm', [FineController::class, 'confirm'])->name('fine.confirm');
    Route::post('/fines/{fine}/adjust',  [FineController::class, 'adjust'])->name('fine.adjust');
    Route::post('/fines/{fine}/pay',     [FineController::class, 'pay'])->name('fine.pay');

    /* ---- Analysis ----
       The item path before the group path, or {group} swallows 'item'. */
    Route::get('/analysis/{code}/item/{item}', [AnalysisController::class, 'item'])->name('analysis.item');
    Route::get('/analysis/{code}/{group}',     [AnalysisController::class, 'group'])->name('analysis.group');

    /* ---- E-Archive ----
       Restoring is destructive enough to belong behind sign-in with
       everything else, not in an open prefix group of its own. */
    Route::get('/archive',                  [ArchiveController::class, 'index'])->name('archive.index');
    Route::get('/archive/case/{reference}', [ArchiveController::class, 'case'])
         ->where('reference', '.*')->name('archive.case');
    Route::put('/archive/inspection/{id}/restore', [ArchiveController::class, 'restoreInspection'])->name('archive.restore.inspection');
    Route::put('/archive/letter/{id}/restore',     [ArchiveController::class, 'restoreDocument'])->name('archive.restore.document');

    /* ---- My account ---- */
    Route::get('/account',             [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/account',             [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/account/signature',  [ProfileController::class, 'storeSignature'])->name('profile.signature');
    Route::delete('/account/signature',[ProfileController::class, 'destroySignature'])->name('profile.signature.destroy');

    /* ---- Administration ---- */
    Route::middleware('can:user.manage')->group(function () {
        Route::get('/users',                        [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create',                 [UserController::class, 'create'])->name('users.create');
        Route::post('/users',                       [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit',            [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}',                 [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle',         [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
    });

    /* ---- Roads ---- */
    Route::middleware('can:road.manage')->group(function () {
        Route::get('/roads',             [RoadController::class, 'index'])->name('road.index');
        Route::get('/roads/add',         [RoadController::class, 'create'])->name('road.create');
        Route::post('/roads',            [RoadController::class, 'store'])->name('road.store');
        Route::get('/roads/{road}/edit', [RoadController::class, 'edit'])->name('road.edit');
        Route::put('/roads/{road}',      [RoadController::class, 'update'])->name('road.update');
        Route::delete('/roads/{road}',   [RoadController::class, 'destroy'])->name('road.destroy');
    });
});