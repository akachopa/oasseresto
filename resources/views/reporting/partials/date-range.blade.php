<form method="GET" class="card card-pad mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="label" for="from">Dari</label>
        <input id="from" name="from" type="date" class="input" value="{{ $from->toDateString() }}">
    </div>
    <div>
        <label class="label" for="to">Sampai</label>
        <input id="to" name="to" type="date" class="input" value="{{ $to->toDateString() }}">
    </div>
    <button class="btn-primary" type="submit">Tampilkan</button>
</form>
