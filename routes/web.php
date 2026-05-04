<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudyMateController;

Route::get('/', [StudyMateController::class, 'dashboard'])->name('dashboard');
Route::post('/subject/add', [StudyMateController::class, 'addSubject'])->name('subject.add');
Route::post('/subject/delete', [StudyMateController::class, 'deleteSubject'])->name('subject.delete');
Route::get('/subject/{id}', [StudyMateController::class, 'subject'])->name('subject.view');
Route::post('/subject/progress', [StudyMateController::class, 'updateProgress'])->name('subject.progress');
Route::post('/note/upload', [StudyMateController::class, 'uploadNote'])->name('note.upload');
Route::post('/note/delete', [StudyMateController::class, 'deleteNote'])->name('note.delete');
Route::get('/note/{id}', [StudyMateController::class, 'note'])->name('note.view');
Route::get('/flashcards', [StudyMateController::class, 'allFlashcards'])->name('flashcards.all');
