
desc "Compile the source into dist/shortstack.php"
task :compile do
  compiled_src = "<?php\n"
  
  %w(VERSION core helpers dispatcher cache flash controller db model model_joiner model_finder finder_matcher document document_finder pager template startup).each do |src_file|
    frag_src = IO.readlines("source/shortstack/#{src_file}.php")
    frag_src.shift # Remove the '<?php' heading...
    if src_file != 'VERSION'
      # Pull out the comments
      commentRe = /(\/\*[^\/]*\*\/)/m
      inlineCommentRe = /(\/\/.*)$/
      multiNewlines = /([\s]*[\n]{2,}[\s]*)$/
      code_src = frag_src.join('')
      stripped_src = code_src.gsub(commentRe, '')
      stripped_src = stripped_src.gsub(inlineCommentRe, '')
      stripped_src = stripped_src.gsub(multiNewlines, '')
      compiled_src += stripped_src
    else
      compiled_src += frag_src.join('')
    end
  end
  
  File.open('dist/shortstack.php', 'w') do |f|
    f.write compiled_src
  end
  
  puts "Done."
end

desc "Builds documentation"
task :docs do
  puts `phpdoc -d source/shortstack -t docs -dn ShortStack -ti "ShortStack Docs"`
  puts "Done."
end

# Show the available tasks by default...
task :default do
  puts `rake -T`
end