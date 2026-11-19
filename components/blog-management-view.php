<?php
/**
 * ServEasy Blog Management Component
 * Reusable across Admin Dashboard and Superadmin Dashboard
 * Targets: blog_posts
 */

if (!isset($pdo)) {
    $cfgPath = file_exists(__DIR__ . '/../common/db_connection.php')
        ? __DIR__ . '/../common/db_connection.php'
        : (file_exists(__DIR__ . '/../../backend/config/db_connection.php') ? __DIR__ . '/../../backend/config/db_connection.php' : null);
    if ($cfgPath) require_once $cfgPath;
    $pdo = Database::connect();
}

$current_admin_name = $_SESSION['admin_name'] ?? ($_SESSION['admin_username'] ?? 'ServEasy Admin');
$current_admin_id   = $_SESSION['admin_id'] ?? null;
$dashboard_endpoint = $is_super_admin ? 'superadmin-dashboard.php' : 'admin-dashboard.php';

// ── Handle Blog AJAX / POST Actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blog_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['blog_action'];

    // 1. Save (Create or Update) Blog Post
    if ($action === 'save_blog') {
        $blog_id     = !empty($_POST['blog_id']) ? (int)$_POST['blog_id'] : 0;
        $title       = trim($_POST['title'] ?? '');
        $category    = trim($_POST['category'] ?? 'Tips & Advice');
        $excerpt     = trim($_POST['excerpt'] ?? '');
        $content     = trim($_POST['content'] ?? '');
        $author_name = trim($_POST['author_name'] ?? $current_admin_name);
        $author_role = trim($_POST['author_role'] ?? 'Service Specialist');
        $tags        = trim($_POST['tags'] ?? '');
        $read_time   = trim($_POST['read_time'] ?? '5 min read');
        $status      = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
        $image_url   = trim($_POST['image_url'] ?? '');

        if (empty($title) || empty($excerpt) || empty($content)) {
            echo json_encode(['ok' => false, 'message' => 'Title, Excerpt, and Content are required.']);
            exit;
        }

        // Generate clean URL slug
        $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        if (empty($base_slug)) {
            $base_slug = 'blog-post-' . time();
        }

        // Handle Image Upload if provided
        $featured_image = !empty($image_url) ? $image_url : './assets/images/blog-1.jpg';
        if (isset($_FILES['featured_image_file']) && $_FILES['featured_image_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['featured_image_file']['tmp_name'];
            $fileName = $_FILES['featured_image_file']['name'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExt, $allowed)) {
                $targetDir = __DIR__ . '/../../frontend-web/pages/assets/images/blogs/';
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $newFileName = 'blog-' . time() . '-' . rand(1000, 9999) . '.' . $fileExt;
                $targetPath  = $targetDir . $newFileName;

                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $featured_image = './assets/images/blogs/' . $newFileName;
                }
            }
        }

        if ($blog_id > 0) {
            // Update existing post
            // Check if slug is unique (excluding current)
            $check = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1");
            $check->execute([$base_slug, $blog_id]);
            $slug = $check->fetchColumn() ? ($base_slug . '-' . $blog_id) : $base_slug;

            // Retain existing image if no new image uploaded or specified
            if (empty($_FILES['featured_image_file']['name']) && empty($image_url)) {
                $img_stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
                $img_stmt->execute([$blog_id]);
                $existing_img = $img_stmt->fetchColumn();
                if (!empty($existing_img)) {
                    $featured_image = $existing_img;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE blog_posts
                SET title = :title,
                    slug = :slug,
                    category = :category,
                    excerpt = :excerpt,
                    content = :content,
                    featured_image = :image,
                    author_name = :author,
                    author_role = :role,
                    tags = :tags,
                    read_time = :read_time,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':title'     => $title,
                ':slug'      => $slug,
                ':category'  => $category,
                ':excerpt'   => $excerpt,
                ':content'   => $content,
                ':image'     => $featured_image,
                ':author'    => $author_name,
                ':role'      => $author_role,
                ':tags'      => $tags,
                ':read_time' => $read_time,
                ':status'    => $status,
                ':id'        => $blog_id
            ]);

            echo json_encode(['ok' => true, 'message' => 'Blog post updated successfully!']);
            exit;
        } else {
            // Insert new post
            $check = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? LIMIT 1");
            $check->execute([$base_slug]);
            $slug = $check->fetchColumn() ? ($base_slug . '-' . time()) : $base_slug;

            $stmt = $pdo->prepare("
                INSERT INTO blog_posts
                (title, slug, category, excerpt, content, featured_image, author_name, author_role, tags, read_time, status, created_by_admin_id)
                VALUES
                (:title, :slug, :category, :excerpt, :content, :image, :author, :role, :tags, :read_time, :status, :admin_id)
            ");
            $stmt->execute([
                ':title'     => $title,
                ':slug'      => $slug,
                ':category'  => $category,
                ':excerpt'   => $excerpt,
                ':content'   => $content,
                ':image'     => $featured_image,
                ':author'    => $author_name,
                ':role'      => $author_role,
                ':tags'      => $tags,
                ':read_time' => $read_time,
                ':status'    => $status,
                ':admin_id'  => $current_admin_id
            ]);

            echo json_encode(['ok' => true, 'message' => 'New blog post published successfully!']);
            exit;
        }
    }

    // 2. Toggle Status (Published / Draft)
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE blog_posts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            echo json_encode(['ok' => true, 'message' => "Post status updated to {$new_status}!"]);
            exit;
        }
    }

    // 3. Delete Blog Post
    if ($action === 'delete_blog') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['ok' => true, 'message' => 'Blog post deleted permanently.']);
            exit;
        }
    }

    // 4. Fetch Single Blog Post for Edit
    if ($action === 'get_blog') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($post) {
            echo json_encode(['ok' => true, 'data' => $post]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Post not found.']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
    exit;
}

// ── Query Posts and Statistics ──────────────────────────────────────────
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_posts,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts,
        COALESCE(SUM(views), 0) as total_views,
        COALESCE(SUM(likes), 0) as total_likes
    FROM blog_posts
")->fetch(PDO::FETCH_ASSOC);

$blogs = $pdo->query("SELECT * FROM blog_posts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- BLOG MANAGEMENT INTERFACE -->
<style>
.blog-mgmt-card {
    border-radius: 14px;
    border: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}
.blog-thumb-img {
    width: 65px;
    height: 48px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.badge-category {
    background: #e6fcf5;
    color: #0ca678;
    border: 1px solid #b2f2bb;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
}
.badge-status-published {
    background: #d3f9d8;
    color: #2b8a3e;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 20px;
}
.badge-status-draft {
    background: #f1f3f5;
    color: #495057;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 20px;
}
.stat-widget {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-left: 4px solid #05c279;
}
.stat-widget.views { border-left-color: #3b82f6; }
.stat-widget.drafts { border-left-color: #f59e0b; }
.stat-widget.likes { border-left-color: #ec4899; }
</style>

<div class="container-fluid px-0">

    <!-- TOP HEADER & ACTION BAR -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-newspaper text-emerald mr-2" style="color: #05c279;"></i>Blog Management
            </h1>
            <p class="text-muted small mb-0">Publish service guides, maintenance tips, and company updates to the public ServEasy blog.</p>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="../frontend-web/pages/blog.php" target="_blank" class="btn btn-outline-secondary mr-2 font-weight-bold" style="border-radius: 8px;">
                <i class="fas fa-external-link-alt mr-1"></i> View Live Blog
            </a>
            <button type="button" class="btn btn-emerald font-weight-bold" style="background: #05c279; color: #fff; border-radius: 8px;" onclick="openBlogModal()">
                <i class="fas fa-plus-circle mr-1"></i> Create New Post
            </button>
        </div>
    </div>

    <!-- METRICS ROW -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-widget">
                <div>
                    <div class="text-xs font-weight-bold text-uppercase text-muted">Total Articles</div>
                    <div class="h4 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_posts'] ?? 0) ?></div>
                </div>
                <i class="fas fa-file-alt fa-2x text-gray-300"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-widget">
                <div>
                    <div class="text-xs font-weight-bold text-uppercase text-muted">Published Live</div>
                    <div class="h4 mb-0 font-weight-bold text-success"><?= number_format($stats['published_posts'] ?? 0) ?></div>
                </div>
                <i class="fas fa-check-circle fa-2x text-success"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-widget drafts">
                <div>
                    <div class="text-xs font-weight-bold text-uppercase text-muted">Drafts</div>
                    <div class="h4 mb-0 font-weight-bold text-warning"><?= number_format($stats['draft_posts'] ?? 0) ?></div>
                </div>
                <i class="fas fa-edit fa-2x text-warning"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="stat-widget views">
                <div>
                    <div class="text-xs font-weight-bold text-uppercase text-muted">Total Article Views</div>
                    <div class="h4 mb-0 font-weight-bold text-primary"><?= number_format($stats['total_views'] ?? 0) ?></div>
                </div>
                <i class="fas fa-chart-line fa-2x text-primary"></i>
            </div>
        </div>
    </div>

    <!-- BLOG POSTS TABLE CARD -->
    <div class="card blog-mgmt-card mb-4">
        <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between border-0">
            <div class="d-flex align-items-center">
                <h6 class="m-0 font-weight-bold text-gray-800 mr-3">All Articles</h6>
                <span class="badge badge-light border text-muted"><?= count($blogs) ?> entries</span>
            </div>
            <div class="d-flex align-items-center mt-2 mt-md-0">
                <input type="text" id="blog-search-input" class="form-control form-control-sm mr-2" style="width: 220px; border-radius: 6px;" placeholder="Search title or category...">
                <select id="blog-status-filter" class="form-control form-control-sm" style="width: 140px; border-radius: 6px;">
                    <option value="all">All Statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Drafts</option>
                </select>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="blog-posts-table">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="width: 70px;">Cover</th>
                            <th>Article Title</th>
                            <th>Category</th>
                            <th>Author</th>
                            <th>Metrics</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-right" style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blogs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-newspaper fa-3x mb-3 text-gray-300 d-block"></i>
                                    <h5>No Blog Posts Yet</h5>
                                    <p class="small mb-3">Get started by creating your first helpful service article or guide.</p>
                                    <button class="btn btn-sm btn-emerald" style="background:#05c279;color:#fff;" onclick="openBlogModal()">Create Post</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blogs as $b): 
                                $webImg = $b['featured_image'];
                                if (strpos($webImg, './assets/') === 0) {
                                    $adminImg = '../frontend-web/pages/' . substr($webImg, 2);
                                } elseif (strpos($webImg, 'http') === 0) {
                                    $adminImg = $webImg;
                                } else {
                                    $adminImg = '../frontend-web/pages/' . ltrim($webImg, '/');
                                }
                            ?>
                                <tr class="blog-row" data-status="<?= htmlspecialchars($b['status']) ?>" data-title="<?= htmlspecialchars(strtolower($b['title'] . ' ' . $b['category'])) ?>">
                                    <td>
                                        <img src="<?= htmlspecialchars($adminImg) ?>" alt="Cover" class="blog-thumb-img" onerror="this.src='../frontend-web/pages/assets/images/blog-1.jpg';">
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-gray-900"><?= htmlspecialchars($b['title']) ?></div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 280px;"><?= htmlspecialchars(substr($b['excerpt'], 0, 75)) ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge-category"><?= htmlspecialchars($b['category']) ?></span>
                                    </td>
                                    <td>
                                        <div class="small font-weight-bold"><?= htmlspecialchars($b['author_name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($b['read_time']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-muted"><i class="fas fa-eye text-primary mr-1"></i> <?= $b['views'] ?> views</div>
                                        <div class="small text-muted"><i class="fas fa-heart text-danger mr-1"></i> <?= $b['likes'] ?> likes</div>
                                    </td>
                                    <td>
                                        <div class="small text-gray-800"><?= date('M j, Y', strtotime($b['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($b['status'] === 'published'): ?>
                                            <span class="badge-status-published">Published</span>
                                        <?php else: ?>
                                            <span class="badge-status-draft">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <a href="../frontend-web/pages/blog.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info p-1 px-2" title="View Public Post">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>

                                        <button type="button" class="btn btn-sm btn-outline-primary p-1 px-2" title="Edit Article" onclick="editBlogPost(<?= $b['id'] ?>)">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm <?= $b['status'] === 'published' ? 'btn-outline-warning' : 'btn-outline-success' ?> p-1 px-2" title="<?= $b['status'] === 'published' ? 'Switch to Draft' : 'Publish Article' ?>" onclick="toggleBlogStatus(<?= $b['id'] ?>, '<?= $b['status'] === 'published' ? 'draft' : 'published' ?>')">
                                            <i class="fas <?= $b['status'] === 'published' ? 'fa-eye-slash' : 'fa-check' ?>"></i>
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" title="Delete Article" onclick="deleteBlogPost(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['title'])) ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- CREATE / EDIT BLOG POST MODAL -->
<div class="modal fade" id="blogPostModal" tabindex="-1" role="dialog" aria-labelledby="blogModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
            <div class="modal-header bg-light" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title font-weight-bold text-gray-800" id="blogModalTitle">
                    <i class="fas fa-pen-nib text-emerald mr-2" style="color: #05c279;"></i>Create New Blog Post
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="blogPostForm" enctype="multipart/form-data">
                <input type="hidden" name="blog_action" value="save_blog">
                <input type="hidden" name="blog_id" id="modal-blog-id" value="0">

                <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                    
                    <!-- Article Title -->
                    <div class="form-group mb-3">
                        <label for="modal-blog-title" class="font-weight-bold text-gray-800">Article Title *</label>
                        <input type="text" name="title" id="modal-blog-title" class="form-control" placeholder="e.g. 5 Maintenance Mistakes That Ruin Your Car Engine" required>
                    </div>

                    <!-- Category & Status -->
                    <div class="form-row mb-3">
                        <div class="col-md-6 form-group">
                            <label for="modal-blog-category" class="font-weight-bold text-gray-800">Category *</label>
                            <select name="category" id="modal-blog-category" class="form-control" required>
                                <option value="Vehicle Care">Vehicle Care</option>
                                <option value="Home & Living">Home & Living</option>
                                <option value="Emergency & Safety">Emergency & Safety</option>
                                <option value="Tips & Advice" selected>Tips & Advice</option>
                                <option value="Lifestyle">Lifestyle</option>
                                <option value="Company News">Company News</option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="modal-blog-status" class="font-weight-bold text-gray-800">Publishing Status *</label>
                            <select name="status" id="modal-blog-status" class="form-control" required>
                                <option value="published">Publish Immediately</option>
                                <option value="draft">Save as Draft</option>
                            </select>
                        </div>
                    </div>

                    <!-- Author Details & Read Time -->
                    <div class="form-row mb-3">
                        <div class="col-md-4 form-group">
                            <label for="modal-blog-author" class="font-weight-bold text-gray-800">Author Name</label>
                            <input type="text" name="author_name" id="modal-blog-author" class="form-control" value="<?= htmlspecialchars($current_admin_name) ?>">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="modal-blog-role" class="font-weight-bold text-gray-800">Author Role / Title</label>
                            <input type="text" name="author_role" id="modal-blog-role" class="form-control" value="Service Specialist">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="modal-blog-readtime" class="font-weight-bold text-gray-800">Estimated Read Time</label>
                            <input type="text" name="read_time" id="modal-blog-readtime" class="form-control" value="5 min read">
                        </div>
                    </div>

                    <!-- Featured Image Upload & URL -->
                    <div class="form-group mb-3 p-3 bg-light rounded border">
                        <label class="font-weight-bold text-gray-800 d-block">Featured Cover Image</label>
                        <div class="form-row align-items-center">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <label for="modal-blog-file" class="small text-muted mb-1">Upload Image (JPG, PNG, WEBP):</label>
                                <input type="file" name="featured_image_file" id="modal-blog-file" accept="image/*" class="form-control-file">
                            </div>
                            <div class="col-md-6">
                                <label for="modal-blog-imgurl" class="small text-muted mb-1">Or Image Path / URL:</label>
                                <input type="text" name="image_url" id="modal-blog-imgurl" class="form-control form-control-sm" placeholder="./assets/images/blog-1.jpg">
                            </div>
                        </div>
                        <div id="modal-img-preview-box" class="mt-2 text-center" style="display: none;">
                            <img id="modal-img-preview" src="" alt="Preview" style="max-height: 120px; border-radius: 8px; border: 1px solid #ddd;">
                        </div>
                    </div>

                    <!-- Short Excerpt -->
                    <div class="form-group mb-3">
                        <label for="modal-blog-excerpt" class="font-weight-bold text-gray-800">Short Summary / Excerpt *</label>
                        <textarea name="excerpt" id="modal-blog-excerpt" class="form-control" rows="2" placeholder="Brief 1-2 sentence preview shown on the blog cards and search results..." required></textarea>
                    </div>

                    <!-- Full Article Content -->
                    <div class="form-group mb-3">
                        <label for="modal-blog-content" class="font-weight-bold text-gray-800">Full Article Content (HTML / Text) *</label>
                        <textarea name="content" id="modal-blog-content" class="form-control font-monospace" rows="8" placeholder="<h2>Subheading</h2><p>Article paragraphs here...</p>" required></textarea>
                        <small class="form-text text-muted">HTML tags such as &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, and &lt;strong&gt; are supported.</small>
                    </div>

                    <!-- Tags -->
                    <div class="form-group mb-0">
                        <label for="modal-blog-tags" class="font-weight-bold text-gray-800">Tags (Comma-separated)</label>
                        <input type="text" name="tags" id="modal-blog-tags" class="form-control" placeholder="e.g. Car Care, Oil Change, Roadside, DIY">
                    </div>

                </div>

                <div class="modal-footer bg-light" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-save-blog" class="btn btn-emerald px-4 font-weight-bold" style="background:#05c279; color:#fff;">
                        <span class="btn-text">Save & Publish</span>
                        <i class="fas fa-check ml-1"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter blog posts table by search & status
$('#blog-search-input').on('keyup', filterBlogTable);
$('#blog-status-filter').on('change', filterBlogTable);

function filterBlogTable() {
    var search = $('#blog-search-input').val().toLowerCase().trim();
    var status = $('#blog-status-filter').val();

    $('.blog-row').each(function() {
        var rowStatus = $(this).data('status');
        var rowText   = $(this).data('title');

        var matchSearch = (search === '' || rowText.indexOf(search) !== -1);
        var matchStatus = (status === 'all' || rowStatus === status);

        if (matchSearch && matchStatus) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

// Open modal for Create
function openBlogModal() {
    $('#blogPostForm')[0].reset();
    $('#modal-blog-id').val(0);
    $('#modal-blog-author').val('<?= htmlspecialchars(addslashes($current_admin_name)) ?>');
    $('#modal-blog-role').val('Service Specialist');
    $('#modal-blog-readtime').val('5 min read');
    $('#modal-blog-status').val('published');
    $('#blogModalTitle').html('<i class="fas fa-pen-nib text-emerald mr-2" style="color: #05c279;"></i>Create New Blog Post');
    $('#modal-img-preview-box').hide();
    $('#blogPostModal').modal('show');
}

// Edit existing blog post
function editBlogPost(id) {
    $.post('<?= $dashboard_endpoint ?>?ajax_action=fetch_list&type=blogs', {
        blog_action: 'get_blog',
        id: id
    }, function(res) {
        if (res && res.ok && res.data) {
            var b = res.data;
            $('#modal-blog-id').val(b.id);
            $('#modal-blog-title').val(b.title);
            $('#modal-blog-category').val(b.category);
            $('#modal-blog-status').val(b.status);
            $('#modal-blog-author').val(b.author_name);
            $('#modal-blog-role').val(b.author_role);
            $('#modal-blog-readtime').val(b.read_time);
            $('#modal-blog-excerpt').val(b.excerpt);
            $('#modal-blog-content').val(b.content);
            $('#modal-blog-tags').val(b.tags);
            $('#modal-blog-imgurl').val(b.featured_image);

            if (b.featured_image) {
                var previewUrl = b.featured_image.indexOf('http') === 0 ? b.featured_image : '../frontend-web/pages/' + b.featured_image.replace(/^\.\//, '');
                $('#modal-img-preview').attr('src', previewUrl);
                $('#modal-img-preview-box').show();
            } else {
                $('#modal-img-preview-box').hide();
            }

            $('#blogModalTitle').html('<i class="fas fa-edit text-emerald mr-2" style="color: #05c279;"></i>Edit Blog Post #' + b.id);
            $('#blogPostModal').modal('show');
        } else {
            alert('Failed to load blog post details.');
        }
    }, 'json').fail(function() {
        alert('Server connection error.');
    });
}

// Toggle blog status
function toggleBlogStatus(id, newStatus) {
    $.post('<?= $dashboard_endpoint ?>?ajax_action=fetch_list&type=blogs', {
        blog_action: 'toggle_status',
        id: id,
        status: newStatus
    }, function(res) {
        if (res && res.ok) {
            currentSection = ''; // reset cached section
            loadSection('blogs');
        } else {
            alert(res.message || 'Status toggle failed.');
        }
    }, 'json');
}

// Delete blog post
function deleteBlogPost(id, title) {
    if (!confirm('Are you sure you want to permanently delete: "' + title + '"?')) {
        return;
    }

    $.post('<?= $dashboard_endpoint ?>?ajax_action=fetch_list&type=blogs', {
        blog_action: 'delete_blog',
        id: id
    }, function(res) {
        if (res && res.ok) {
            currentSection = '';
            loadSection('blogs');
        } else {
            alert(res.message || 'Delete failed.');
        }
    }, 'json');
}

// Save Blog Post Form Submit
$('#blogPostForm').on('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var saveBtn = $('#btn-save-blog');
    saveBtn.prop('disabled', true).find('.btn-text').text('Saving...');

    $.ajax({
        url: '<?= $dashboard_endpoint ?>?ajax_action=fetch_list&type=blogs',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(res) {
            saveBtn.prop('disabled', false).find('.btn-text').text('Save & Publish');
            if (res && res.ok) {
                $('#blogPostModal').modal('hide');
                currentSection = '';
                loadSection('blogs');
            } else {
                alert(res.message || 'Failed to save blog post.');
            }
        },
        error: function(xhr, status, error) {
            saveBtn.prop('disabled', false).find('.btn-text').text('Save & Publish');
            alert('Server error while saving: ' + error);
        }
    });
});

// Image file preview
$('#modal-blog-file').on('change', function() {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#modal-img-preview').attr('src', e.target.result);
            $('#modal-img-preview-box').show();
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>
