<x-mail::message>
# Good company for your collection.

<p>{{ $sender->name }} ({{ '@'.$sender->handle }}) would like to be your friend on Plaat Club.</p>

Friends can see collections shared with Friends and their new additions. Collections set to Only me stay private.

<x-mail::button :url="$links['accept']">
Accept friend request
</x-mail::button>

[Decline friend request]({{ $links['decline'] }})

<x-slot:subcopy>
These private links expire in seven days and work without signing in. You’ll confirm your choice on Plaat Club. Don’t forward this email.
</x-slot:subcopy>
</x-mail::message>
