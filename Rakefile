
desc "Compile the source into dist/shortstack.php"
task :compile do
  compiled_src = "<?php\n"
  
  %w(VERSION core helpers dispatcher cache controller db finder model document template startup).each do |src_file|
    frag_src = IO.readlines("source/shortstack/#{src_file}.php")
    frag_src.shift
    compiled_src += frag_src.join('')
  end
  
  File.open('dist/shortstack.php', 'w') do |f|
    f.write compiled_src
  end
  
  puts "Done."
end

# Show the available tasks by default...
task :default do
  puts `rake -T`
end