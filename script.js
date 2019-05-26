const { home_url, user_name } = simple_sse_chat_data;

const es = new EventSource(`${home_url}/wp-admin/admin-ajax.php?action=event_streame`);

// 表示
let lastId = 0;
es.addEventListener("message", e => {
    const { chat_data } = JSON.parse(e.data);

    // 更新がなければ何もしない
    if (lastId === (chat_data[0] ? chat_data[0].id : 0)) return;

    targetElement = document.getElementById("js-simple-sse-chat-body").querySelector("tbody");
    targetElement.innerHTML = ""
    chat_data.forEach(data => {
        const { user_id, content } = data
        targetElement.insertAdjacentHTML("afterbegin", `
            <tr>
                <td>
                    <small>${user_name}</small>
                    <br>
                    <strong>${content}</strong>
                </td>
            </tr>
        `)
    })

    const scrollElement = document.querySelector(".simple-sse-chat-container")
    scrollElement.scrollTop = scrollElement.scrollHeight
    lastId = (chat_data[0] ? chat_data[0].id : 0);
});

// 登録
document.getElementById("js-simple-sse-chat-form").addEventListener("submit", async e => {
    e.preventDefault();
    formData = new FormData(e.target)

    // 入力値が空の場合は何もしない
    if (formData.get("chat-content") === "") return;

    // 登録のリクエスト
    const res = await fetch(`${home_url}/wp-admin/admin-ajax.php?action=chat_post`, {
        method: "POST",
        body: formData,
    })
    
    // 入力欄を空にしからにしておく
    document.getElementById("js-simple-sse-chat-input").value = ""
});
