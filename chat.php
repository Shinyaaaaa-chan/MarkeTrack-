<?php
// /admin/chat.php
// Make sure admin session_name('admin_session') is set before session_start() in your app.
session_start();
include '../db_connection.php';

// fetch list of customers (simple example)
$customers = $conn->query("SELECT id, fullname FROM customers ORDER BY fullname LIMIT 200");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Chat</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
.container{ display:flex; gap:12px; }
.sidebar{ width:240px; border:1px solid #eee; padding:8px; background:#fff; height:420px; overflow:auto; }
.chat{ flex:1; border:1px solid #eee; padding:8px; background:#fff; display:flex; flex-direction:column; }
#conversation{ flex:1; overflow-y:auto; padding:6px; }
.msg-right .bubble { background:#007bff; color:#fff; display:inline-block; padding:8px 12px; border-radius:10px; max-width:80%; }
.msg-left .bubble { background:#f1f1f1; color:#333; display:inline-block; padding:8px 12px; border-radius:10px; max-width:80%; }
.controls{ display:flex; gap:8px; margin-top:8px; }
.controls textarea{ flex:1; }
</style>
</head>
<body>
<h3>Admin Chat (Assistant)</h3>
<div class="container">
    <div class="sidebar">
        <h4>Customers</h4>
        <?php while($c = $customers->fetch_assoc()): ?>
            <div class="cust" data-id="<?= $c['id'] ?>" style="padding:8px; cursor:pointer; border-bottom:1px solid #f1f1f1;">
                <?= htmlspecialchars($c['fullname']) ?>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="chat">
        <div id="conversation"><p style="text-align:center;color:#888;">Choose a customer to start</p></div>
        <div class="controls">
            <textarea id="message" rows="2" placeholder="Type message"></textarea>
            <button id="sendBtn">Send</button>
        </div>
    </div>
</div>

<script>
var currentCustomerId = 0;

function loadConversation(){
    if (!currentCustomerId) return;
    $('#conversation').html('<p style="text-align:center;color:#888;">Loading...</p>');
    $.get('../fetch_admin_messages.php', { conversation_with_type: 'customer', conversation_with_id: currentCustomerId }, function(html){
        $('#conversation').html(html);
        $('#conversation').scrollTop($('#conversation')[0].scrollHeight);
    });
}

$(function(){
    $('.cust').on('click', function(){
        $('.cust').css('background','');
        $(this).css('background','#f5f5f5');
        currentCustomerId = $(this).data('id');
        loadConversation();
        // poll for new messages for this customer
        if (window.poller) clearInterval(window.poller);
        window.poller = setInterval(loadConversation, 8000);
    });

    $('#sendBtn').on('click', function(){
        var msg = $('#message').val().trim();
        if (!msg || !currentCustomerId) return alert('Select a customer and type a message.');
        $.post('../send_message.php', { message: msg, receiver_id: currentCustomerId, receiver_type: 'customer', customer_id: currentCustomerId }, function(resp){
            if (resp.success) {
                $('#message').val('');
                loadConversation();
            } else {
                alert(resp.error || 'Failed to send.');
            }
        }, 'json').fail(function(){ alert('Request failed.'); });
    });
});
</script>
</body>
</html>
