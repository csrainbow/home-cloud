using System;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Net;
using System.Net.Http;
using System.Threading;
using System.Windows.Forms;

class ProgressStream : Stream {
    Stream inner;
    long total;
    Action<long, long> onProgress;
    long readBytes;
    public ProgressStream(Stream inner, long total, Action<long, long> onProgress) { this.inner = inner; this.total = total; this.onProgress = onProgress; }
    public override bool CanRead { get { return true; } }
    public override bool CanSeek { get { return false; } }
    public override bool CanWrite { get { return false; } }
    public override long Length { get { return total; } }
    public override long Position { get { return readBytes; } set { throw new NotSupportedException(); } }
    public override int Read(byte[] buffer, int offset, int count) { int r = inner.Read(buffer, offset, count); readBytes += r; onProgress(readBytes, total); return r; }
    public override void Flush() { inner.Flush(); }
    public override long Seek(long offset, SeekOrigin origin) { throw new NotSupportedException(); }
    public override void SetLength(long value) { throw new NotSupportedException(); }
    public override void Write(byte[] buffer, int offset, int count) { throw new NotSupportedException(); }
}

public class CloudSyncApp {
    static string ConfigPath;
    static Config config;
    static List<FileItem> fileQueue = new List<FileItem>();
    static HttpClient client = new HttpClient { Timeout = TimeSpan.FromHours(4) };
    static bool isUploading = false;

    [STAThread]
    public static void Main() {
        ConfigPath = Path.Combine(Path.GetDirectoryName(Application.ExecutablePath), "config.json");
        LoadConfig();
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        try {
            Application.Run(BuildForm());
        } catch (Exception ex) {
            File.WriteAllText(Path.Combine(Path.GetDirectoryName(Application.ExecutablePath), "crash.log"), ex.ToString());
        }
    }

    class Theme {
        public Color Bg, BgPanel, BgInput, Border;
        public Color Fg, FgDim, FgMuted, FgAccent;
        public static Theme Dark = new Theme {
            Bg = Color.FromArgb(15, 17, 26), BgPanel = Color.FromArgb(20, 24, 38), BgInput = Color.FromArgb(25, 30, 50),
            Border = Color.FromArgb(30, 35, 55), Fg = Color.FromArgb(220, 220, 240),
            FgDim = Color.FromArgb(140, 140, 170), FgMuted = Color.FromArgb(100, 100, 130),
            FgAccent = Color.FromArgb(59, 130, 246)
        };
        public static Theme Light = new Theme {
            Bg = Color.FromArgb(240, 242, 245), BgPanel = Color.FromArgb(255, 255, 255), BgInput = Color.FromArgb(255, 255, 255),
            Border = Color.FromArgb(208, 213, 221), Fg = Color.FromArgb(26, 26, 46),
            FgDim = Color.FromArgb(107, 114, 128), FgMuted = Color.FromArgb(156, 163, 175),
            FgAccent = Color.FromArgb(37, 99, 235)
        };
    }
    static Theme CurrentTheme = Theme.Dark;

    class Config {
        public string ip = "192.168.2.162";
        public string port = "3000";
        public string username = "admin";
        public string password = "admin";
        public bool darkMode = true;
    }

    class FileItem {
        public string path;
        public string name;
        public long size;
        public string status = "waiting";
        public int progress;
        public bool cancelled;
        public bool paused;
        public CancellationTokenSource cts;
    }

    static void LoadConfig() {
        config = new Config();
        try {
            if (File.Exists(ConfigPath)) {
                string json = File.ReadAllText(ConfigPath);
                config.ip = GetJsonValue(json, "ip") ?? config.ip;
                config.port = GetJsonValue(json, "port") ?? config.port;
                config.username = GetJsonValue(json, "username") ?? config.username;
                config.password = GetJsonValue(json, "password") ?? config.password;
                string dm = GetJsonValue(json, "darkMode");
                if (dm != null) config.darkMode = dm == "true";
            }
        } catch { }
        CurrentTheme = config.darkMode ? Theme.Dark : Theme.Light;
    }

    static void SaveConfig() {
        try {
            string json = string.Format("{{\"server\":{{\"ip\":\"{0}\",\"port\":\"{1}\",\"username\":\"{2}\",\"password\":\"{3}\"}},\"darkMode\":\"{4}\"}}", config.ip, config.port, config.username, config.password, config.darkMode ? "true" : "false");
            File.WriteAllText(ConfigPath, json);
        } catch { }
    }

    static void ApplyTheme(Control c) {
        Theme t = CurrentTheme;
        if (c is Form) { c.BackColor = t.Bg; }
        else if (c is GroupBox) { c.BackColor = t.BgPanel; c.ForeColor = t.FgMuted; }
        else if (c is Panel) { c.BackColor = t.BgPanel; }
        else if (c is TextBox) { var tb = (TextBox)c; tb.BackColor = t.BgInput; tb.ForeColor = t.Fg; }
        else if (c is ListView) { var lv = (ListView)c; lv.BackColor = Color.FromArgb(t == Theme.Dark ? 18 : 245, t == Theme.Dark ? 22 : 247, t == Theme.Dark ? 36 : 250); lv.ForeColor = t.Fg; }
        else if (c is Label && !(c is LinkLabel)) { c.ForeColor = t.FgDim; c.BackColor = Color.Transparent; }
        else if (c is ProgressBar) { var pb = (ProgressBar)c; pb.BackColor = t.Border; pb.ForeColor = Color.FromArgb(59, 130, 246); }
        foreach (Control child in c.Controls) ApplyTheme(child);
    }

    static void StyleButton(Button b) {
        b.FlatStyle = FlatStyle.Flat;
        b.FlatAppearance.BorderSize = 0;
        b.UseVisualStyleBackColor = false;
        b.Font = new Font("Segoe UI", 9, FontStyle.Bold);
        b.Height = 30;
    }

    static string GetJsonValue(string json, string key) {
        int idx = json.IndexOf("\"" + key + "\"");
        if (idx < 0) return null;
        int start = json.IndexOf("\"", idx + key.Length + 2) + 1;
        if (start <= 0) return null;
        int end = json.IndexOf("\"", start);
        if (end < 0) return null;
        return json.Substring(start, end - start);
    }

    static Form BuildForm() {
        Form form = new Form();
        form.Text = "Cloud Sync Uploader";
        form.Size = new Size(530, 580);
        form.MinimumSize = new Size(480, 420);
        form.StartPosition = FormStartPosition.CenterScreen;
        form.BackColor = CurrentTheme.Bg;
        form.Icon = GetAppIcon();

        // ========== BOTTOM LAYER (added first, docked last) ==========

        // Progress bar
        var progBar = new ProgressBar { Dock = DockStyle.Bottom, Height = 4, Style = ProgressBarStyle.Continuous };
        progBar.ForeColor = Color.FromArgb(59, 130, 246);
        progBar.BackColor = CurrentTheme.Border;
        form.Controls.Add(progBar);

        // Log section
        var logTitle = new Label { Text = "Log", Font = new Font("Segoe UI", 9, FontStyle.Bold), ForeColor = CurrentTheme.Fg, Dock = DockStyle.Bottom, Height = 18, Padding = new Padding(14, 2, 0, 0), BackColor = CurrentTheme.BgPanel };
        var logBox = new Panel { Dock = DockStyle.Bottom, Height = 100, BackColor = CurrentTheme.BgPanel };
        var txtLog = new TextBox { Multiline = true, Dock = DockStyle.Fill, ReadOnly = true, BackColor = Color.FromArgb(config.darkMode ? 12 : 242, config.darkMode ? 14 : 244, config.darkMode ? 26 : 247), ForeColor = CurrentTheme.FgDim, BorderStyle = BorderStyle.None, Font = new Font("Consolas", 9) };
        logBox.Controls.Add(txtLog);
        form.Controls.Add(logBox);
        form.Controls.Add(logTitle);

        // Upload bar
        var uploadPanel = new Panel { Dock = DockStyle.Bottom, Height = 46, BackColor = CurrentTheme.BgPanel };
        var sepLine = new Label { Dock = DockStyle.Top, Height = 1, BackColor = CurrentTheme.Border };
        uploadPanel.Controls.Add(sepLine);
        Button btnUpload = MakeButton("Upload All", Color.FromArgb(52, 211, 153));
        btnUpload.Location = new Point(14, 8);
        var lblProg = new Label { Text = "Ready", Font = new Font("Segoe UI", 9), ForeColor = CurrentTheme.FgDim, Location = new Point(164, 12), Size = new Size(340, 22), BackColor = Color.Transparent };
        uploadPanel.Controls.Add(btnUpload);
        uploadPanel.Controls.Add(lblProg);
        form.Controls.Add(uploadPanel);

        // ========== CONNECTION SECTION ==========

        var connBox = new Panel { BackColor = CurrentTheme.BgPanel, Dock = DockStyle.Top, Height = 145 };
        var connAccent = new Label { Dock = DockStyle.Top, Height = 3, BackColor = Color.FromArgb(59, 130, 246) };
        var connTitle = new Label { Text = "Connection", Font = new Font("Segoe UI", 10, FontStyle.Bold), ForeColor = CurrentTheme.Fg, Location = new Point(14, 12), Size = new Size(96, 18), BackColor = Color.Transparent };
        connBox.Controls.Add(connAccent);
        connBox.Controls.Add(connTitle);

        var themeBtn = new Button { Text = "Dark", Font = new Font("Segoe UI", 7), ForeColor = CurrentTheme.FgDim, BackColor = Color.Transparent, FlatStyle = FlatStyle.Flat, Size = new Size(44, 18), Location = new Point(440, 12), UseVisualStyleBackColor = false, TextAlign = ContentAlignment.MiddleCenter };
        themeBtn.FlatAppearance.BorderSize = 0;
        themeBtn.FlatAppearance.MouseOverBackColor = Color.FromArgb(40, 40, 60, 80);

        var txtIP = MakeText(config.ip); txtIP.Location = new Point(92, 38); txtIP.Size = new Size(130, 24);
        var txtPort = MakeText(config.port); txtPort.Location = new Point(296, 38); txtPort.Size = new Size(60, 24);
        var txtUser = MakeText(config.username); txtUser.Location = new Point(92, 68); txtUser.Size = new Size(130, 24);
        var txtPass = MakeText(config.password, true); txtPass.Location = new Point(296, 68); txtPass.Size = new Size(130, 24);

        var lblIP = new Label { Text = "Server IP", Location = new Point(14, 40), Size = new Size(76, 20), Font = new Font("Segoe UI", 9), ForeColor = CurrentTheme.FgDim, BackColor = Color.Transparent, TextAlign = ContentAlignment.MiddleRight };
        var lblPort = new Label { Text = "Port", Location = new Point(238, 40), Size = new Size(56, 20), Font = new Font("Segoe UI", 9), ForeColor = CurrentTheme.FgDim, BackColor = Color.Transparent, TextAlign = ContentAlignment.MiddleRight };
        var lblUser = new Label { Text = "Username", Location = new Point(14, 70), Size = new Size(76, 20), Font = new Font("Segoe UI", 9), ForeColor = CurrentTheme.FgDim, BackColor = Color.Transparent, TextAlign = ContentAlignment.MiddleRight };
        var lblPass = new Label { Text = "Password", Location = new Point(238, 70), Size = new Size(56, 20), Font = new Font("Segoe UI", 9), ForeColor = CurrentTheme.FgDim, BackColor = Color.Transparent, TextAlign = ContentAlignment.MiddleRight };

        var btnTest = MakeButton("  Test Connection  ", Color.FromArgb(59, 130, 246));
        btnTest.Location = new Point(14, 104);
        var lblStatus = new Label { Text = "Disconnected", Location = new Point(164, 106), Size = new Size(200, 20), Font = new Font("Segoe UI", 8), ForeColor = Color.FromArgb(239, 68, 68), BackColor = Color.Transparent };

        connBox.Controls.Add(txtIP); connBox.Controls.Add(txtPort); connBox.Controls.Add(txtUser); connBox.Controls.Add(txtPass);
        connBox.Controls.Add(lblIP); connBox.Controls.Add(lblPort); connBox.Controls.Add(lblUser); connBox.Controls.Add(lblPass);
        connBox.Controls.Add(btnTest); connBox.Controls.Add(lblStatus); connBox.Controls.Add(themeBtn);
        form.Controls.Add(connBox);

        // ========== FILE SECTION ==========

        var fileBox = new Panel { BackColor = CurrentTheme.BgPanel };
        var fileTitle = new Label { Text = "Files", Font = new Font("Segoe UI", 10, FontStyle.Bold), ForeColor = CurrentTheme.Fg, Location = new Point(14, 10), Size = new Size(60, 18), BackColor = Color.Transparent };
        var btnAdd = MakeButton("+ Add Files", Color.FromArgb(59, 130, 246));
        btnAdd.Location = new Point(14, 32);
        var btnClear = MakeButton("Clear", Color.FromArgb(239, 68, 68));
        btnClear.Location = new Point(130, 32);
        fileBox.Controls.Add(fileTitle);
        fileBox.Controls.Add(btnAdd);
        fileBox.Controls.Add(btnClear);
        var fileList = new ListView { Location = new Point(0, 68), Anchor = AnchorStyles.Top | AnchorStyles.Bottom | AnchorStyles.Left | AnchorStyles.Right, View = View.Details, BackColor = Color.FromArgb(config.darkMode ? 18 : 245, config.darkMode ? 22 : 247, config.darkMode ? 36 : 250), ForeColor = CurrentTheme.Fg, BorderStyle = BorderStyle.None, FullRowSelect = true, GridLines = false };
        fileList.Columns.Add("File", -2);
        fileList.Columns.Add("Size", 80);
        fileList.Columns.Add("Status", 80);
        fileList.Columns.Add("", 28);
        fileList.Columns.Add("", 28);
        fileBox.Controls.Add(fileList);
        form.Controls.Add(fileBox);

        Action PositionFileBox = () => {
            int connBottom = connBox.Top + connBox.Height;
            fileBox.Location = new Point(0, connBottom);
            fileBox.Width = form.ClientSize.Width;
            fileBox.Height = form.ClientSize.Height - connBottom - uploadPanel.Height - logBox.Height - logTitle.Height - progBar.Height;
        };
        form.Resize += (s, e) => PositionFileBox();
        form.Shown += (s, e) => PositionFileBox();

        // ========== EVENT HANDLERS ==========

        txtIP.TextChanged += (s, e) => { config.ip = txtIP.Text; SaveConfig(); };
        txtPort.TextChanged += (s, e) => { config.port = txtPort.Text; SaveConfig(); };
        txtUser.TextChanged += (s, e) => { config.username = txtUser.Text; SaveConfig(); };
        txtPass.TextChanged += (s, e) => { config.password = txtPass.Text; SaveConfig(); };

        btnTest.Click += async (s, e) => {
            btnTest.Enabled = false; btnTest.Text = "Testing...";
            lblStatus.Text = "Testing..."; lblStatus.ForeColor = Color.FromArgb(250, 204, 21);
            try {
                string url = "http://" + txtIP.Text + ":" + txtPort.Text;
                var resp = await client.GetAsync(url, new CancellationTokenSource(5000).Token);
                if (resp.IsSuccessStatusCode) { lblStatus.Text = "Connected"; lblStatus.ForeColor = Color.FromArgb(52, 211, 153); }
                else { lblStatus.Text = "Server error: " + (int)resp.StatusCode; lblStatus.ForeColor = Color.FromArgb(239, 68, 68); }
            } catch (Exception ex) { lblStatus.Text = "Failed: " + ex.Message; lblStatus.ForeColor = Color.FromArgb(239, 68, 68); }
            btnTest.Enabled = true; btnTest.Text = "Test Connection";
        };

        Action<string> Log = (msg) => {
            if (txtLog.IsDisposed) return;
            txtLog.AppendText("[" + DateTime.Now.ToString("HH:mm:ss") + "] " + msg + Environment.NewLine);
            txtLog.SelectionStart = txtLog.TextLength;
            txtLog.ScrollToCaret();
        };

        Action RefreshFileList = () => {
            fileList.Items.Clear();
            foreach (var fi in fileQueue) {
                string sizeStr = fi.size > 1073741824 ? (fi.size / 1073741824.0).ToString("N2") + " GB"
                               : fi.size > 1048576 ? (fi.size / 1048576.0).ToString("N1") + " MB"
                               : (fi.size / 1024.0).ToString("N0") + " KB";
                string statusStr = fi.status == "waiting" ? "Waiting" : fi.status == "uploading" ? fi.progress + "%" : fi.status == "done" ? "OK" : fi.status == "failed" ? "Failed" : fi.status;
                string cancelStr = (!fi.cancelled && fi.status != "done") ? "X" : "";
                string pauseStr = "";
                if (!fi.cancelled && fi.status != "done" && fi.status != "failed") {
                    pauseStr = fi.paused ? ">" : "||";
                }
                var item = new ListViewItem(new[] { fi.name, sizeStr, statusStr, cancelStr, pauseStr });
                item.ForeColor = fi.status == "done" ? Color.FromArgb(52, 211, 153) : fi.status == "failed" ? Color.FromArgb(239, 68, 68) : fi.status == "uploading" ? Color.FromArgb(96, 165, 250) : Color.FromArgb(200, 200, 220);
                fileList.Items.Add(item);
            }
        };

        btnAdd.Click += (s, e) => {
            var ofd = new OpenFileDialog { Multiselect = true };
            if (ofd.ShowDialog() == DialogResult.OK) {
                foreach (var f in ofd.FileNames) {
                    var fi = new FileInfo(f);
                    if (!fileQueue.Exists(x => x.path == f)) fileQueue.Add(new FileItem { path = f, name = fi.Name, size = fi.Length });
                }
                RefreshFileList();
                Log(ofd.FileNames.Length + " file(s) added");
            }
        };

        btnClear.Click += (s, e) => { fileQueue.Clear(); RefreshFileList(); Log("Queue cleared"); };

        fileList.MouseClick += (s, e) => {
            var hit = fileList.HitTest(e.X, e.Y);
            if (hit.Item != null && hit.SubItem != null) {
                int colIdx = hit.Item.SubItems.IndexOf(hit.SubItem);
                int fileIdx = hit.Item.Index;
                if (fileIdx < 0 || fileIdx >= fileQueue.Count) return;
                var fi = fileQueue[fileIdx];
                if (colIdx == 3 && hit.SubItem.Text == "X") {
                    fi.cancelled = true;
                    fi.status = "cancelled";
                    if (fi.cts != null) { try { fi.cts.Cancel(); } catch { } }
                    Log("Cancelled: " + fi.name);
                    RefreshFileList();
                } else if (colIdx == 4 && (hit.SubItem.Text == "||" || hit.SubItem.Text == ">")) {
                    fi.paused = !fi.paused;
                    fi.status = fi.paused ? "paused" : "waiting";
                    if (fi.paused && fi.cts != null) { try { fi.cts.Cancel(); } catch { } }
                    Log(fi.paused ? "Paused: " + fi.name : "Resumed: " + fi.name);
                    RefreshFileList();
                }
            }
        };

        btnUpload.Click += async (s, e) => {
            if (isUploading) { Log("Already uploading"); return; }
            if (fileQueue.Count == 0) { Log("No files to upload"); return; }
            isUploading = true; btnUpload.Enabled = false; progBar.Value = 0;
            int done = 0, failed = 0;

            while (true) {
                FileItem fi = null;
                foreach (var f in fileQueue) {
                    if (f.status == "waiting" && !f.paused && !f.cancelled) { fi = f; break; }
                }
                if (fi == null) break;
                int total = fileQueue.Count - fileQueue.FindAll(f => f.cancelled).Count;
                fi.status = "uploading"; fi.progress = 0; RefreshFileList();
                string sizeStr = fi.size > 1048576 ? (fi.size / 1048576.0).ToString("N1") + " MB" : (fi.size / 1024.0).ToString("N0") + " KB";
                Log("Uploading: " + fi.name + " (" + sizeStr + ")");

                long uploadedBytes = 0;
                long lastBytes = 0;
                DateTime lastTime = DateTime.Now;
                System.Windows.Forms.Timer progTimer = new System.Windows.Forms.Timer { Interval = 200 };
                progTimer.Tick += (s2, e2) => {
                    try {
                        if (!form.IsDisposed) {
                            form.BeginInvoke((Action)(() => {
                                if (fi.status == "uploading") {
                                    double secs = (DateTime.Now - lastTime).TotalSeconds;
                                    long delta = uploadedBytes - lastBytes;
                                    double speed = secs > 0 ? delta / secs / 1024.0 : 0;
                                    string speedStr = speed > 1024 ? (speed / 1024.0).ToString("N1") + " MB/s" : speed.ToString("N0") + " KB/s";
                                    lblProg.Text = string.Format("{0:N1} MB / {1:N1} MB  {2}", uploadedBytes / 1048576.0, fi.size / 1048576.0, speedStr);
                                    lastBytes = uploadedBytes;
                                    lastTime = DateTime.Now;
                                }
                                RefreshFileList();
                            }));
                        }
                    } catch { }
                };

                try {
                    fi.cts = new CancellationTokenSource();
                    string url = "http://" + config.ip + ":" + config.port + "/api/media/upload?username=" + config.username + "&password=" + config.password;
                    using (var formData = new MultipartFormDataContent())
                    using (var stream = File.OpenRead(fi.path))
                    using (var progStream = new ProgressStream(stream, fi.size, (read, totalBytes) => { uploadedBytes = read; fi.progress = (int)(read * 100 / Math.Max(1L, totalBytes)); })) {
                        progTimer.Start();
                        formData.Add(new StreamContent(progStream), "file", fi.name);
                        formData.Add(new StringContent(config.username), "username");
                        formData.Add(new StringContent(config.password), "password");
                        var httpReq = (HttpWebRequest)WebRequest.Create(url);
                        httpReq.Method = "POST";
                        httpReq.AllowWriteStreamBuffering = false;
                        httpReq.SendChunked = true;
                        httpReq.ContentType = formData.Headers.ContentType.ToString();
                        httpReq.Headers["user"] = config.username;
                        httpReq.Headers["pass"] = config.password;
                        httpReq.Timeout = (int)TimeSpan.FromHours(4).TotalMilliseconds;
                        fi.cts.Token.Register(() => { try { httpReq.Abort(); } catch { } });
                        using (var reqStream = await httpReq.GetRequestStreamAsync()) {
                            await formData.CopyToAsync(reqStream);
                        }
                        fi.cts.Token.ThrowIfCancellationRequested();
                        using (var httpResp = (HttpWebResponse)await httpReq.GetResponseAsync()) {
                            progTimer.Stop();
                            int statusCode = (int)httpResp.StatusCode;
                            if (statusCode >= 200 && statusCode < 300) { fi.status = "done"; done++; Log("OK: " + fi.name); }
                            else { fi.status = "failed"; failed++; Log("FAIL: " + fi.name + " - HTTP " + statusCode); }
                        }
                    }
                } catch (Exception ex) {
                    try { progTimer.Stop(); } catch { }
                    if (fi.cancelled || fi.paused) { Log((fi.cancelled ? "Cancelled" : "Paused") + ": " + fi.name); }
                    else { fi.status = "failed"; failed++; Log("FAIL: " + fi.name + " - " + ex.Message); }
                }
                progTimer.Dispose();
                fi.progress = 100;
                int processed = done + failed;
                int total2 = fileQueue.Count - fileQueue.FindAll(f => f.cancelled).Count;
                progBar.Value = processed * 100 / Math.Max(1, total2);
                lblProg.Text = done + " OK, " + failed + " Failed (" + processed + "/" + total2 + ")";
                RefreshFileList();
            }
            isUploading = false; btnUpload.Enabled = true;
            Log("Upload complete");
        };

        // Theme toggle
        themeBtn.Click += (s, e) => {
            config.darkMode = !config.darkMode;
            CurrentTheme = config.darkMode ? Theme.Dark : Theme.Light;
            ApplyTheme(form);
            SaveConfig();
            themeBtn.Text = config.darkMode ? "Dark" : "Light";
            themeBtn.ForeColor = CurrentTheme.FgDim;
            connTitle.ForeColor = CurrentTheme.Fg;
            fileTitle.ForeColor = CurrentTheme.Fg;
            logTitle.ForeColor = CurrentTheme.Fg;
            lblProg.ForeColor = CurrentTheme.FgDim;
            progBar.ForeColor = Color.FromArgb(59, 130, 246);
            bool connected = lblStatus.Text == "Connected";
            lblStatus.ForeColor = connected ? Color.FromArgb(52, 211, 153) : Color.FromArgb(239, 68, 68);
        };
        ApplyTheme(form);
        themeBtn.Text = config.darkMode ? "Dark" : "Light";

        return form;
    }

    static TextBox MakeText(string text, bool isPassword = false) {
        Theme t = CurrentTheme;
        return new TextBox { Text = text, Font = new Font("Segoe UI", 9), ForeColor = t.Fg, BackColor = t.BgInput, BorderStyle = BorderStyle.FixedSingle, UseSystemPasswordChar = isPassword };
    }

    static Button MakeButton(string text, Color color) {
        var b = new Button { Text = text, BackColor = color, ForeColor = Color.White };
        b.FlatStyle = FlatStyle.Flat;
        b.FlatAppearance.BorderSize = 0;
        b.UseVisualStyleBackColor = false;
        b.Font = new Font("Segoe UI", 9, FontStyle.Bold);
        b.Height = 30;
        b.Width = TextRenderer.MeasureText(text, b.Font).Width + 24;
        return b;
    }

    static Icon GetAppIcon() {
        string icoPath = Path.Combine(Path.GetDirectoryName(Application.ExecutablePath), "app.ico");
        if (File.Exists(icoPath)) { try { return new Icon(icoPath); } catch { } }
        return null;
    }
}
