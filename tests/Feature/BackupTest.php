<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->backupDir = storage_path('app/private/backups');
    $this->databasePath = database_path('database.sqlite');
    
    // Ensure we have a database to copy
    if (!File::exists($this->databasePath)) {
        File::put($this->databasePath, '');
    }
});

afterEach(function () {
    // Clean up backups
    if (File::exists($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }
});

it('redirects guests from backups index', function () {
    $response = $this->get(route('backups.index'));
    $response->assertRedirect(route('login'));
});

it('lists backups on the index page for authenticated users', function () {
    // Create a fake backup
    File::makeDirectory($this->backupDir, 0755, true, true);
    File::put($this->backupDir . '/backup_test.sqlite', 'test');
    
    $response = $this->actingAs($this->user)->get(route('backups.index'));
    
    $response->assertStatus(200);
});

it('can create a backup', function () {
    expect(File::exists($this->databasePath))->toBeTrue();

    $response = $this->actingAs($this->user)->post(route('backups.store'));
    
    $response->assertRedirect();
    $response->assertSessionHas('success');
    
    $files = File::files($this->backupDir);
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toStartWith('backup_');
});

it('can delete a backup', function () {
    File::makeDirectory($this->backupDir, 0755, true, true);
    File::put($this->backupDir . '/backup_test.sqlite', 'test');
    
    $response = $this->actingAs($this->user)->delete(route('backups.destroy', ['file' => 'backup_test.sqlite']));
    
    $response->assertRedirect();
    $response->assertSessionHas('success');
    
    $files = File::exists($this->backupDir) ? File::files($this->backupDir) : [];
    expect($files)->toBeEmpty();
});

it('can download a backup', function () {
    File::makeDirectory($this->backupDir, 0755, true, true);
    File::put($this->backupDir . '/backup_test.sqlite', 'test data');
    
    $response = $this->actingAs($this->user)->get(route('backups.download', ['file' => 'backup_test.sqlite']));
    
    $response->assertDownload('backup_test.sqlite');
});

it('can upload a backup', function () {
    $file = \Illuminate\Http\UploadedFile::fake()->create('my_backup.sqlite', 100);
    
    $response = $this->actingAs($this->user)->post(route('backups.upload'), [
        'backup' => $file,
    ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('success');
    
    $files = File::files($this->backupDir);
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toStartWith('backup_uploaded_');
});

it('rejects invalid file types on upload', function () {
    $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100);
    
    $response = $this->actingAs($this->user)->post(route('backups.upload'), [
        'backup' => $file,
    ]);
    
    $response->assertRedirect();
    $response->assertSessionHas('error');
});
