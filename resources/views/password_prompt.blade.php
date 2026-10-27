<!DOCTYPE html>
<html>
<head>
    <title>Enter Password</title>
</head>
<body>
    <h1>Password Required</h1>
    <p>This content is password protected. Please enter the password to view it.</p>

    @if ($errors->has('password'))
        <p style="color: red;">{{ $errors->first('password') }}</p>
    @endif

    <form method="POST" action="{{ url()->current() }}">
        @csrf
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
