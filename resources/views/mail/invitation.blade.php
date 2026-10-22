<x-mail::message>
# Good records. Better company.

{{ $sender->name }} ({{ '@'.$sender->handle }}) invited you to Plaat Club, a home for your record collection and the people you share it with.

Create an account and verify this email address to receive their friend request. You decide whether to accept. Already have an account? Sign in and verify your email.

<x-mail::button :url="$url">
See your invitation
</x-mail::button>

This invitation expires in seven days. Becoming friends never changes your collection’s visibility. Collections set to Only me stay private.

Keep good company,<br>
Plaat Club
</x-mail::message>
