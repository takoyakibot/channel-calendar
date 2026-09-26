<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IgnoredTerm;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\UnmatchedTerm;
use App\Support\StreamTagger;
use App\Support\TermAlreadyClassified;
use App\Support\TermClassifier;
use App\Support\TermNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Dictionary maintenance: the destructive half of issue #51 (rename, merge, remove, un-ignore). */
class TagController extends Controller
{
    public function index(): View
    {
        return view('admin.tags.index', [
            'tags' => Tag::with('aliases')->withCount('streams')->orderBy('kind')->orderBy('name')->get(),
            'ignored' => IgnoredTerm::orderBy('display')->get(),
            'unmatched' => UnmatchedTerm::orderByDesc('count')->orderBy('term')->get(),
        ]);
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('tags', 'name')->ignore($tag->id)],
            'kind' => ['required', Rule::in(Tag::KINDS)],
        ]);

        $old = $tag->only('name', 'kind');
        $tag->update(['name' => trim($validated['name']), 'kind' => $validated['kind']]);
        $alias = TermNormalizer::normalize($tag->name);
        if (! TagAlias::where('alias', $alias)->exists()) {
            $tag->aliases()->create(['alias' => $alias, 'created_by_user_id' => $request->user()->id]);
        }
        ActivityLog::record($request->user()->id, 'update_tag', Tag::class, $tag->id, ['from' => $old, 'to' => $tag->only('name', 'kind')]);
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', 'タグを更新しました。');
    }

    public function addAlias(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate(['alias' => 'required|string|max:80']);
        $alias = TermNormalizer::normalize($validated['alias']);
        if (mb_strlen($alias) < 2) {
            return redirect('/admin/tags')->with('error', 'エイリアスが短すぎます。');
        }
        if ($existing = TagAlias::with('tag')->where('alias', $alias)->first()) {
            return redirect('/admin/tags')->with('error', "「{$alias}」は既に「{$existing->tag->name}」のエイリアスです。");
        }
        $tag->aliases()->create(['alias' => $alias, 'created_by_user_id' => $request->user()->id]);
        ActivityLog::record($request->user()->id, 'add_tag_alias', Tag::class, $tag->id, ['alias' => $alias]);
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', "「{$alias}」を「{$tag->name}」に追加しました。");
    }

    public function destroyAlias(Request $request, TagAlias $tagAlias): RedirectResponse
    {
        ActivityLog::record($request->user()->id, 'remove_tag_alias', Tag::class, $tagAlias->tag_id, ['alias' => $tagAlias->alias]);
        $tagAlias->delete();
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', 'エイリアスを削除しました。');
    }

    /** Fold $tag into another: aliases and stream links move over, then $tag disappears. */
    public function merge(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate(['into_tag_id' => ['required', 'integer', 'exists:tags,id', Rule::notIn([$tag->id])]]);
        $into = Tag::findOrFail($validated['into_tag_id']);

        TagAlias::where('tag_id', $tag->id)->update(['tag_id' => $into->id]);
        foreach ($tag->streams()->get() as $stream) {
            if (! $into->streams()->where('streams.id', $stream->id)->exists()) {
                $into->streams()->attach($stream->id, ['source' => $stream->pivot->source]);
            }
        }
        $tag->streams()->detach();
        ActivityLog::record($request->user()->id, 'merge_tag', Tag::class, $into->id, ['from' => $tag->name, 'into' => $into->name]);
        $tag->delete();
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', "「{$tag->name}」を「{$into->name}」に統合しました。");
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        ActivityLog::record($request->user()->id, 'delete_tag', Tag::class, $tag->id, ['name' => $tag->name, 'kind' => $tag->kind]);
        $tag->delete();
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', 'タグを削除しました。');
    }

    public function unignore(Request $request, IgnoredTerm $ignoredTerm): RedirectResponse
    {
        ActivityLog::record($request->user()->id, 'unignore_term', null, null, ['term' => $ignoredTerm->term]);
        $ignoredTerm->delete();
        app(StreamTagger::class)->retagAll();

        return redirect('/admin/tags')->with('success', "「{$ignoredTerm->display}」を未分類に戻しました。");
    }

    public function classify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'term' => 'required|string|max:80',
            'kind' => ['required', Rule::in(TermClassifier::KINDS)],
            'tag_id' => 'nullable|integer|exists:tags,id',
            'name' => 'nullable|string|max:60',
        ]);

        try {
            $tag = TermClassifier::classify($validated['term'], $validated['kind'], $validated['tag_id'] ?? null, $validated['name'] ?? null, $request->user()->id);
        } catch (TermAlreadyClassified $e) {
            return redirect('/admin/tags')->with('error', 'その語句は分類済みです。');
        }

        return redirect('/admin/tags')->with('success', $tag ? "「{$validated['term']}」を「{$tag->name}」に分類しました。" : "「{$validated['term']}」を無視しました。");
    }
}
