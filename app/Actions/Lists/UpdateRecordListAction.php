<?php

namespace App\Actions\Lists;

use App\Models\RecordList;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateRecordListAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, RecordList $recordList, array $attributes): void
    {
        abort_unless($recordList->user_id === $user->id, 404);

        $data = Validator::make($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visibility' => ['sometimes', 'required', Rule::in(['only_me', 'friends', 'everyone'])],
            'recordIds' => ['sometimes', 'array', 'list', 'max:1000'],
            'recordIds.*' => ['required', 'integer', 'distinct', Rule::exists('collection_records', 'id')->where('user_id', $user->id)],
        ])->validate();

        DB::transaction(function () use ($recordList, $data): void {
            $recordList = RecordList::query()->lockForUpdate()->findOrFail($recordList->id);
            $recordList->update(Arr::only($data, ['name', 'description', 'visibility']));
            if (array_key_exists('recordIds', $data)) {
                $entries = [];
                foreach ($data['recordIds'] as $position => $id) {
                    $entries[$id] = ['position' => $position];
                }
                $recordList->records()->sync($entries);
                DB::table('record_list_entries')->where('record_list_id', $recordList->id)->whereNull('collection_record_id')->delete();
                $recordList->touch();
            }
        });
    }
}
