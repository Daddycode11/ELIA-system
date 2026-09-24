<form method="post" action="<?= escape(url('actions/requests/save.php')) ?>" class="card card-body mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= escape((string) ($request['id'] ?? '')) ?>">
    <input type="hidden" name="request_type_id" value="<?= (int) $typeId ?>">
    <input type="hidden" name="revision" value="<?= (int) ($request['revision'] ?? 0) ?>">
    <h2 class="h5">Request information</h2>
    <p class="small text-secondary">Drafts may be incomplete. All fields below are required before submission. The request type and checklist are fixed when the draft is created.</p>
    <div class="row g-3">
        <div class="col-12"><label for="title" class="form-label">Title</label><input id="title" name="title" class="form-control" maxlength="200" value="<?= escape($fields['title'] ?? '') ?>"></div>
        <div class="col-12"><label for="purpose" class="form-label">Purpose</label><textarea id="purpose" name="purpose" class="form-control" rows="4" maxlength="10000"><?= escape($fields['purpose'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label for="destination" class="form-label">Destination / venue</label><input id="destination" name="destination" class="form-control" maxlength="200" value="<?= escape($fields['destination'] ?? '') ?>"></div>
        <div class="col-md-6"><label for="country" class="form-label">Country</label><input id="country" name="country" class="form-control" maxlength="100" value="<?= escape($fields['country'] ?? '') ?>"></div>
        <div class="col-md-6"><label for="start_date" class="form-label">Start date</label><input id="start_date" name="start_date" type="date" class="form-control" value="<?= escape($fields['start_date'] ?? '') ?>"></div>
        <div class="col-md-6"><label for="end_date" class="form-label">End date</label><input id="end_date" name="end_date" type="date" class="form-control" value="<?= escape($fields['end_date'] ?? '') ?>"></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit"><?= isset($request['id']) ? 'Save changes' : 'Save draft' ?></button></div>
</form>
