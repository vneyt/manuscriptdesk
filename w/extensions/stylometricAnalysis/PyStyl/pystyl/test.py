"""
Ideas: The output directory should be a temporary directory, refreshed or deleted every few hours. If the user saves the result, it should be moved to a temporary place
"""

from phpserialize import *
import sys
import os
import json
import pprint
import ast

#from pystyl.corpus import Corpus
#from pystyl.analysis import pca, tsne, distance_matrix, hierarchical_clustering, vnc_clustering, bootstrapped_distance_matrices, bootstrap_consensus_tree
#from pystyl.visualization import scatterplot, scatterplot_3d, clustermap, scipy_dendrogram, ete_dendrogram, bct_dendrogram


input = sys.argv[1]

# Load the data that PHP sent us
try:
   data = ast.literal_eval(input)
except:
    print "ERROR " + input
    sys.exit(1)

print data
print 'SUCCESS'

# Send it to stdout (to PHP)



# def getArgs(number):
#   try:
#     argument = os.path.expanduser(sys.argv[1])
#   except IndexError, e:
#     return sys.exit('noargs')
#   return argument
#
# def decodeJson(input):
#   try:
#     json_object = json.loads(input)
#   except ValueError, e:
#     return sys.exit('notjson')
#   return json_object
#
# argument = getArgs(1)
# loaded_json = decodeJson(argument)
#
#
# print loaded_json